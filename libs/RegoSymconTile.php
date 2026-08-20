<?php

declare(strict_types=1);

/**
 * Gemeinsame Basis aller REGOsymcon-Kacheln.
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
trait RegoSymconTile
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
     * Ein gespeicherter Zielwert, im Typ der Variable.
     *
     * Symcons Bedienelement "SelectValue" legt ihn als JSON ab (true, 50,
     * "Text"). Ältere Konfigurationen tragen dort noch die Beschriftung --
     * "Aus", "An" --, die bleibt lesbar.
     */
    protected function RegoZielwert(int $variableID, $roh)
    {
        if (!is_string($roh)) {
            return $roh;
        }
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return $roh;
        }

        $dekodiert = json_decode($roh, true);
        if (($dekodiert === null) && (trim($roh) !== 'null')) {
            return $this->RegoAusText($variableID, $roh);
        }

        switch (IPS_GetVariable($variableID)['VariableType']) {
            case 0:
                return (bool) $dekodiert;
            case 1:
                return (int) $dekodiert;
            case 2:
                return (float) $dekodiert;
            default:
                return (string) $dekodiert;
        }
    }

    /**
     * Wie ein Wert in einer Liste steht: so, wie Symcon ihn überall zeigt --
     * mit den Beschriftungen und der Einheit des Profils.
     */
    protected function RegoWertText(int $variableID, $wert): string
    {
        if (($variableID > 0) && IPS_VariableExists($variableID)) {
            $text = @GetValueFormattedEx($variableID, $wert);
            if (is_string($text) && ($text !== '')) {
                return $text;
            }
        }
        if (is_bool($wert)) {
            return $wert ? '1' : '0';
        }

        return (string) $wert;
    }

    /**
     * Ein als Text hinterlegter Zielwert bekommt den Typ der Variable.
     * Komma und Punkt gelten beide als Dezimaltrenner.
     */
    private function RegoAusText(int $variableID, string $text)
    {
        switch (IPS_GetVariable($variableID)['VariableType']) {
            case 0:
                // Zuerst die Beschriftungen des Profils ("An", "Auf"), danach
                // die üblichen Schreibweisen.
                $profil = $this->RegoProfil($variableID);
                $gesucht = mb_strtolower(trim($text));
                if ($profil !== null) {
                    foreach ($profil['Associations'] as $association) {
                        $name = $association['Name'];
                        if ((mb_strtolower($name) === $gesucht)
                            || (mb_strtolower(IPS_Translate($this->InstanceID, $name)) === $gesucht)) {
                            return (bool) $association['Value'];
                        }
                    }
                }
                return in_array($gesucht, ['1', 'true', 'an', 'ja', 'ein', 'auf'], true);
            case 1:
                return (int) round((float) str_replace(',', '.', $text));
            case 2:
                return (float) str_replace(',', '.', $text);
            default:
                return $text;
        }
    }

    /**
     * Das wirksame Profil einer Ja/Nein-Variable, sonst null.
     */
    private function RegoProfil(int $variableID): ?array
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }
        $variable = IPS_GetVariable($variableID);
        if ($variable['VariableType'] != 0) {
            return null;
        }

        $name = $variable['VariableCustomProfile'];
        if ($name === '') {
            $name = $variable['VariableProfile'];
        }
        if (($name === '') || !IPS_VariableProfileExists($name)) {
            return null;
        }

        $profil = IPS_GetVariableProfile($name);

        return empty($profil['Associations']) ? null : $profil;
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
     * Kachelchen-Raster: je Eintrag ein Feld mit Etikett und Wert.
     *
     * $zeilen ist eine Liste von Zeilen, jede Zeile eine Liste aus
     * ['label' => ..., 'text' => ...]. Was in einer Zeile steht, bleibt
     * nebeneinander -- so teilen sich etwa die drei Spannungen eine Zeile.
     */
    protected function RegoFelder(array $zeilen): string
    {
        // Alle Zeilen bekommen dieselbe Spaltenzahl -- die laengste Zeile gibt
        // sie vor. Eine kuerzere Zeile laesst rechts eine Luecke, statt ihre
        // Felder breitzuziehen; so sind alle Felder gleich breit.
        $spalten = 1;
        foreach ($zeilen as $zeile) {
            $spalten = max($spalten, count($zeile));
        }

        $html = '';
        foreach ($zeilen as $zeile) {
            if (empty($zeile)) {
                continue;
            }
            $felder = '';
            foreach ($zeile as $eintrag) {
                $felder .= '<span class="feld">'
                    . '<span class="feld-label">' . htmlspecialchars((string) $eintrag['label']) . '</span>'
                    . '<span class="feld-wert">' . htmlspecialchars((string) $eintrag['text']) . '</span>'
                    . '</span>';
            }
            $html .= '<div class="feld-zeile" style="grid-template-columns:repeat('
                . $spalten . ',minmax(0,1fr))">' . $felder . '</div>';
        }

        return '<div class="felder" id="rego-felder">' . $html . '</div>';
    }

    protected function RegoFelderScript(): string
    {
        return <<<'JS'
function regoRenderFelder(zeilen) {
    window.regoState.Felder = zeilen;
    var raster = document.getElementById('rego-felder');
    raster.innerHTML = '';

    var spalten = 1;
    (zeilen || []).forEach(function (zeile) {
        if (zeile && zeile.length > spalten) {
            spalten = zeile.length;
        }
    });

    (zeilen || []).forEach(function (zeile) {
        if (!zeile || !zeile.length) {
            return;
        }
        var reihe = document.createElement('div');
        reihe.className = 'feld-zeile';
        reihe.style.gridTemplateColumns = 'repeat(' + spalten + ',minmax(0,1fr))';

        zeile.forEach(function (eintrag) {
            var feld = document.createElement('span');
            feld.className = 'feld';

            var label = document.createElement('span');
            label.className = 'feld-label';
            label.textContent = eintrag.label;

            var wert = document.createElement('span');
            wert.className = 'feld-wert';
            wert.textContent = eintrag.text;

            feld.appendChild(label);
            feld.appendChild(wert);
            reihe.appendChild(feld);
        });

        raster.appendChild(reihe);
    });
}
window.regoHandlers['Felder'] = regoRenderFelder;
regoRenderFelder(window.regoState.Felder);
JS;
    }

    /**
     * Werteraster: Zahlen mit Beschriftung, darunter Ja/Nein-Werte als Pillen.
     * Genutzt von Wetterstation und Zaehler -- beide zeigen eine Liste von
     * Messwerten, die sich nur im Inhalt unterscheidet.
     */
    protected function RegoRasterHtml(): string
    {
        return '<div class="werte" id="rego-werte"></div>'
            . '<div class="badges" id="rego-badges"></div>';
    }

    protected function RegoRasterScript(): string
    {
        return <<<'JS'
function regoRenderRaster(daten) {
    window.regoState.Raster = daten;

    var werte = document.getElementById('rego-werte');
    werte.innerHTML = '';
    (daten.values || []).forEach(function (eintrag) {
        var block = document.createElement('span');
        block.className = 'wert';

        var zahl = document.createElement('span');
        zahl.className = 'wert-zahl';
        zahl.textContent = eintrag.text;
        if (eintrag.unit) {
            var einheit = document.createElement('span');
            einheit.className = 'wert-einheit';
            einheit.textContent = eintrag.unit;
            zahl.appendChild(einheit);
        }

        var label = document.createElement('span');
        label.className = 'wert-label';
        label.textContent = eintrag.label;

        block.appendChild(zahl);
        block.appendChild(label);
        werte.appendChild(block);
    });

    var badges = document.getElementById('rego-badges');
    badges.innerHTML = '';
    (daten.badges || []).forEach(function (badge) {
        var span = document.createElement('span');
        span.className = 'badge' + (badge.alarm ? ' badge-danger' : '');
        span.textContent = badge.text;
        badges.appendChild(span);
    });
}
window.regoHandlers['Raster'] = regoRenderRaster;
regoRenderRaster(window.regoState.Raster);
JS;
    }

    /**
     * Baut aus den Listenzeilen (VariableID, Label, Digits, Alarm) die
     * Anzeige: Zahlen ins Raster, Ja/Nein-Werte in die Pillen. Was wohin
     * gehoert, entscheidet der Typ der Variable, nicht eine Namensliste.
     */
    protected function RegoRasterState(array $zeilen): array
    {
        $values = [];
        $badges = [];

        foreach ($zeilen as $zeile) {
            $variableID = (int) ($zeile['VariableID'] ?? 0);
            if (($variableID == 0) || !IPS_VariableExists($variableID)) {
                continue;
            }

            $variable = IPS_GetVariable($variableID);
            $label = trim((string) ($zeile['Label'] ?? ''));
            if ($label === '') {
                $label = IPS_GetName($variableID);
            }
            $wert = GetValue($variableID);

            if ($variable['VariableType'] == 0) {
                $badges[] = [
                    'text' => $label . ': ' . ($wert ? 'Ja' : 'Nein'),
                    'alarm' => ((bool) $wert) && ((bool) ($zeile['Alarm'] ?? false)),
                ];
                continue;
            }

            $profil = $variable['VariableCustomProfile'];
            if ($profil === '') {
                $profil = $variable['VariableProfile'];
            }
            $info = ($profil !== '') && IPS_VariableProfileExists($profil) ? IPS_GetVariableProfile($profil) : null;

            if ($variable['VariableType'] == 3) {
                $values[] = ['text' => (string) $wert, 'unit' => '', 'label' => $label];
                continue;
            }

            $stellen = (int) ($zeile['Digits'] ?? -1);
            if ($stellen < 0) {
                $stellen = ($info !== null) ? (int) $info['Digits'] : (($variable['VariableType'] == 1) ? 0 : 1);
            }

            $einheit = trim((string) ($zeile['Unit'] ?? ''));
            if ($einheit === '') {
                $einheit = ($info !== null) ? trim((string) $info['Suffix']) : '';
            }

            $values[] = [
                'text' => number_format((float) $wert, max(0, $stellen), ',', ''),
                'unit' => $einheit,
                'label' => $label,
            ];
        }

        return ['values' => $values, 'badges' => $badges];
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
            'zaehler'  => '<path d="M3 3h18v18H3z"/><path d="M7 12h10"/><path d="M12 7v10"/>',
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

/* Aktive Szene: alle Mitglieder stehen auf ihrem Zielwert */
.buttons button.szene-aktiv{
    background:var(--accent-bg); border-color:var(--accent); color:var(--accent);
}

/* Der URL-Aufruf zeigt entweder die Seite selbst oder einen Knopf -- beides
   nimmt die ganze Kachel ein, statt oben als schmaler Streifen zu sitzen. */
.tile[data-type="url"]{align-items:stretch}
.tile[data-type="url"] .stack{flex:1 1 auto; min-height:0}
.tile[data-type="url"] .buttons{flex:1 1 auto; min-height:0}
.tile[data-type="url"] .knopf-link{height:100%; font-size:1rem}
.tile[data-type="url"] .seite{
    flex:1 1 auto; width:100%; min-height:0; display:block;
    border:1px solid var(--border); border-radius:var(--radius-lg);
    background:var(--surface-3);
}

/* Zeitschaltuhr: je Schaltpunkt eine Zeile, in der Visu bedienbar */
.punkte{display:flex; flex-direction:column; gap:.3rem; width:100%; min-height:0; overflow:auto}
.punkt{
    display:flex; flex-direction:column; gap:.25rem; width:100%;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:.3rem .4rem;
}
.punkt-reihe{display:flex; align-items:center; gap:.35rem; width:100%; min-width:0}
.punkt-luecke{flex:1 1 auto}
.punkt-aus{opacity:.5}
.punkt-an{
    flex:0 0 auto; width:14px; height:14px; padding:0; border-radius:50%;
    border:1px solid var(--border); background:var(--surface-hover); cursor:pointer;
}
.punkt-an-ein{background:var(--accent); border-color:var(--accent)}
.punkt-zeit{
    flex:0 0 auto; font-size:.82rem; font-weight:600; color:var(--text);
    font-variant-numeric:tabular-nums; background:transparent;
    border:1px solid transparent; border-radius:var(--radius-sm); padding:.05rem .15rem;
}
button.punkt-zeit{background:var(--surface-2); border-color:var(--border)}
.punkt-astro{white-space:nowrap; color:var(--text-faint); font-weight:500}
.punkt-tage{display:flex; gap:.1rem; flex:0 0 auto}
.tag{
    width:1.15rem; padding:0; font-size:.6rem; line-height:1.3; cursor:pointer;
    background:transparent; border:1px solid transparent; border-radius:var(--radius-sm);
    color:var(--text-faint);
}
.tag-an{background:var(--surface-hover); border-color:var(--border); color:var(--text); font-weight:700}

.punkt-leer{font-size:.85rem; color:var(--text-faint)}

/* Schmale Kachel -- auf dem Telefon ist jede Zeile sonst nicht mehr zu treffen
   und die Auswahl nicht mehr zu lesen. */
@media (max-width: 460px) {
    .waehler-knopf{font-size:.9rem; padding:.35rem .45rem}
    .waehler-eintrag{font-size:1.05rem; min-height:2.8rem}
    .tag{width:1.6rem; font-size:.8rem; line-height:1.7}
    .punkt-an{width:18px; height:18px}
    .punkt-weg{width:1.8rem; font-size:1.2rem}
    .punkt-fuss button{min-height:2.2rem; font-size:.85rem}
}
.waehler-knopf{
    font-size:.72rem; color:var(--text); background:var(--surface-2);
    border:1px solid var(--border); border-radius:var(--radius-sm);
    padding:.12rem .3rem; max-width:100%; cursor:pointer; text-align:left;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.waehler-knopf:hover{border-color:var(--accent); color:var(--accent)}

/* Auswahlblatt: liegt ueber der Kachel, weil das Auswahlfeld des Browsers
   sich im Rahmen der Kachel nicht aufklappen laesst. */
/* Das Blatt bleibt innerhalb der Polsterung der Kachel: oben schreibt die
   Visualisierung ihren eigenen Namen ueber die Flaeche, rechts liegt ihr
   Symbol zum Vergroessern. Beides wuerde alles verdecken, was dort steht. */
.waehler{
    position:absolute; z-index:5; display:flex; flex-direction:column;
    top:var(--pad-top,8px); right:var(--pad-side,8px);
    bottom:var(--pad-bottom,8px); left:var(--pad-side,8px);
    background:var(--surface-1); border-radius:var(--radius-lg); padding:.3rem;
}
.waehler[hidden]{display:none}
.waehler-kopf{
    display:flex; align-items:center; gap:.5rem;
    font-size:.85rem; font-weight:600; color:var(--text-faint);
    padding:0 2.8rem .3rem .2rem;
}
/* Schliessen sitzt unten: oben deckt der Name der Kachel alles zu, rechts
   das Symbol zum Vergroessern. Unten stoert nichts. */
.waehler-zu{
    flex:0 0 auto; width:100%; margin-top:.3rem; min-height:2.2rem;
    padding:0 .5rem; font-size:.9rem; font-weight:600; cursor:pointer;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-sm); color:var(--text);
}
.waehler-zu:hover{border-color:var(--accent); color:var(--accent)}
.waehler-liste{display:flex; flex-direction:column; gap:.25rem; overflow:auto; min-height:0}
.waehler-eintrag{
    text-align:left; font-size:1rem; line-height:1.3; padding:.55rem .6rem;
    min-height:2.4rem; cursor:pointer;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-sm); color:var(--text);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.waehler-eintrag:hover{border-color:var(--accent); color:var(--accent)}
.waehler-hier{background:var(--surface-hover); border-color:var(--accent); font-weight:700}

/* Uhrzeit: Stunde und Minute nebeneinander, jede Spalte scrollt fuer sich */
.waehler-spalten{display:flex; gap:.4rem; min-height:0; height:100%; overflow:hidden}
.waehler-spalte{
    flex:1 1 0; display:flex; flex-direction:column; gap:.2rem;
    overflow:auto; min-height:0; position:relative;
}
.waehler-spalte-kopf{
    position:sticky; top:0; z-index:1; padding:.1rem .2rem;
    background:var(--surface-1); color:var(--text-faint);
    font-size:.72rem; font-weight:600; text-align:center;
}
.waehler-zahl{text-align:center; font-variant-numeric:tabular-nums}
.punkt-art{flex:0 0 auto}
/* Die Ziel-Auswahl darf schrumpfen, aber nicht verschwinden -- ohne
   Mindestbreite quetschen die Wochentage sie in einer schmalen Kachel auf
   null, und dann sieht sie aus wie ein leeres Feld. */
.punkt-zielwahl{flex:1 1 auto; min-width:5.5rem}
.punkt-wert{flex:0 0 auto; max-width:7rem}

.punkt-wert-leer{flex:0 0 auto; font-size:.72rem; color:var(--text-faint)}
.punkt-offset{min-width:4rem}
.punkt-weg{
    flex:0 0 auto; width:1.2rem; padding:0; line-height:1;
    background:transparent; border:1px solid transparent; border-radius:var(--radius-sm);
    color:var(--text-faint); font-size:.95rem; cursor:pointer;
}
.punkt-weg:hover{color:var(--danger); border-color:var(--danger-border)}
.punkt-fuss{display:flex; gap:.4rem; width:100%}
.punkt-fuss button{
    flex:1 1 0; min-height:1.7rem; font-size:.72rem; cursor:pointer;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-sm); color:var(--text);
}
.punkt-fuss button:hover{border-color:var(--accent); color:var(--accent)}
.tile[data-type="zeitschaltuhr"]{position:relative}
.tile[data-type="zeitschaltuhr"] .stack{flex:1 1 auto; min-height:0}

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

/* Kachelchen-Raster wie die Dashboard-Karten in REGObase: gedaempftes
   Etikett oben, Wert darunter, alles in kleinen Feldern nebeneinander.
   Die Schrift ist etwas groesser als dort -- in REGObase sitzt die Karte in
   einer dichten Seitenspalte, hier steht sie allein in einer Symcon-Kachel. */
/* Zeile ueber den Feldern, etwa das Datum -- steht direkt unter der
   Ueberschrift, die Symcon selbst zeichnet. */
.kopfzeile{
    width:100%; font-size:1.6rem; font-weight:600; letter-spacing:-.01em;
    color:var(--text-muted);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.felder{display:flex; flex-direction:column; gap:.4rem; width:100%}
/* Eine Zeile je Gruppe: die drei Spannungen bleiben nebeneinander, auch wenn
   es eng wird -- die Spaltenzahl steht fest, nicht auto-fit. */
.feld-zeile{display:grid; gap:.4rem; width:100%}
.feld{
    display:flex; flex-direction:column; gap:.1rem; overflow:hidden;
    background:var(--surface-3); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:.35rem .45rem; min-width:0;
}
.feld-label{
    font-size:.68rem; line-height:1.15; color:var(--text-faint);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.feld-wert{
    font-size:.85rem; font-weight:600; line-height:1.2; color:var(--text);
    font-variant-numeric:tabular-nums; word-break:break-word;
}

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
