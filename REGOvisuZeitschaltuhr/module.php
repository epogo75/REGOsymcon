<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoVisuTile.php';

/**
 * Zeitschaltuhr -- eine Instanz je Uhr.
 *
 * Vorbild ist die Zeitschaltuhr von REGObaseX1: eine Tages- oder Wochenuhr mit
 * beliebig vielen Schaltpunkten, jeder mit fester Uhrzeit oder mit
 * Sonnenauf-/-untergang samt Verschiebung. Ziel ist entweder eine Variable,
 * die einen Wert bekommt, oder eine Szene, die aufgerufen wird.
 *
 * Verpasste Schaltungen holt die Uhr nicht nach -- wie in der Vorlage. Nach
 * einem Neustart zaehlt erst wieder, was von jetzt an faellig wird.
 */
class REGOvisuZeitschaltuhr extends IPSModule
{
    use RegoVisuTile;

    private const TAGE = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    private const STANDORT_GUID = '{45E97A63-F870-408A-B259-2933F7EABF74}';
    private const SZENE_GUID = '{458A32A4-E74F-45D1-89D3-C31EFC98FEB2}';

    // Zeigt das Ziel auf eine REGOvisu-Kachel, ist die Variable gemeint, die
    // sie bedient -- im Raum steht die Kachel, nicht die Gruppenadresse.
    private const KACHEL_ZIEL = [
        '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}' => 'SwitchVariable',    // Schalten
        '{A4507CF7-C921-467C-BD01-699C862B9F5C}' => 'DimVariable',       // Dimmen
        '{23F455EC-9236-480B-B02F-E10CE43DBDE2}' => 'PositionVariable',  // Jalousie
        '{FEB37553-F02A-4F1B-A669-15BCD71E0712}' => 'SetpointVariable',  // Klima
        '{2562CE14-21C3-4609-B04E-A9A69C51C684}' => 'TriggerVariable',   // Taster
    ];

    // Weiter als eine Stunde plant die Uhr nicht voraus: so wandern
    // Sonnenzeiten, Sommerzeit und Aenderungen an den Punkten von selbst mit.
    private const VORLAUF_MAX = 3600;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('Mode', 1);
        $this->RegisterPropertyString('Points', '[]');
        $this->RegisterPropertyInteger('LocationInstance', 0);

        $this->RegisterAttributeInteger('LastRun', 0);
        $this->RegisterAttributeString('Skips', '{}');

        $this->RegisterTimer('Schalten', 0, 'RGVZU_Feuern($_IPS[\'TARGET\']);');

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $neu = (@IPS_GetObjectIDByIdent('Aktiv', $this->InstanceID) === false);
        $this->RegisterVariableBoolean('Aktiv', 'Aktiv', '~Switch', 0);
        $this->EnableAction('Aktiv');
        if ($neu) {
            // Eine frisch angelegte Uhr laeuft -- alles andere waere eine
            // stille Falle, wenn die Punkte schon stehen.
            $this->SetValue('Aktiv', true);
        }

        $this->RegisterVariableString('Naechste', 'Nächste Schaltung', '', 1);

        // Kein Nachholen: was waehrend eines Neustarts faellig war, ist vorbei.
        $this->WriteAttributeInteger('LastRun', time());

        $this->Planen();
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case 'Aktiv':
                $this->SetValue('Aktiv', (bool) $Value);
                $this->Planen();
                break;

            case 'Skip':
                $this->Ueberspringen();
                break;
        }
    }

    // ------------------------------------------------------------------
    // Konfigurationsformular
    // ------------------------------------------------------------------

    /**
     * Die Spalten "Tage", "Zeit" und "Ziel" entstehen beim Anzeigen neu -- ein
     * verschobener Sonnenuntergang oder eine geloeschte Variable soll man in
     * der Liste sehen, ohne die Zeile anzufassen.
     */
    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        foreach ($form['elements'] as $index => $element) {
            if (($element['name'] ?? '') === 'Points') {
                $form['elements'][$index]['values'] = $this->Beschriftet($this->Punkte());
            }
        }

        return json_encode($form);
    }

    /**
     * Das Bearbeitungsformular einer Zeile.
     *
     * Der Zielwert bekommt "SelectValue" -- Symcons Bedienelement, das sich
     * nach dem Profil der Zielvariable richtet. Eine Szene braucht keinen
     * Wert, dann bleibt das Feld verborgen.
     */
    public function Zeilenformular(int $TargetID, int $TimeType): string
    {
        $variable = $this->Zielvariable($TargetID);
        $astro = ($TimeType != 0);

        $wochentage = [];
        foreach (self::TAGE as $nummer => $kuerzel) {
            $wochentage[] = [
                'type'    => 'CheckBox',
                'name'    => 'D' . $nummer,
                'caption' => $kuerzel,
            ];
        }

        return json_encode([
            [
                'type'    => 'CheckBox',
                'name'    => 'Active',
                'caption' => 'Aktiv',
            ],
            [
                'type'    => 'RowLayout',
                'visible' => ($this->ReadPropertyInteger('Mode') == 1),
                'items'   => $wochentage,
            ],
            [
                'type'    => 'Select',
                'name'    => 'TimeType',
                'caption' => 'Zeitpunkt',
                'options' => [
                    ['caption' => 'Feste Uhrzeit', 'value' => 0],
                    ['caption' => 'Sonnenaufgang', 'value' => 1],
                    ['caption' => 'Sonnenuntergang', 'value' => 2],
                ],
                'onChange' => 'RGVZU_ZeileZeitart($id, $TimeType);',
            ],
            [
                'type'    => 'SelectTime',
                'name'    => 'Time',
                'caption' => 'Uhrzeit',
                'visible' => !$astro,
            ],
            [
                'type'    => 'NumberSpinner',
                'name'    => 'Offset',
                'caption' => 'Verschiebung',
                'minimum' => -720,
                'maximum' => 720,
                'suffix'  => 'min',
                'visible' => $astro,
            ],
            [
                'type'     => 'SelectObject',
                'name'     => 'TargetID',
                'caption'  => 'Ziel: Variable oder Szene',
                'onChange' => 'RGVZU_ZeileZiel($id, $TargetID);',
            ],
            [
                'type'       => 'SelectValue',
                'name'       => 'Value',
                'caption'    => 'Zielwert',
                'variableID' => $variable,
                'visible'    => ($variable > 0),
            ],
        ]);
    }

    /**
     * Im Bearbeitungsformular wurde die Zeitart gewechselt: Uhrzeit und
     * Verschiebung schliessen sich gegenseitig aus.
     */
    public function ZeileZeitart(int $TimeType): void
    {
        $this->UpdateFormField('Time', 'visible', $TimeType == 0);
        $this->UpdateFormField('Offset', 'visible', $TimeType != 0);
    }

    /**
     * Im Bearbeitungsformular wurde ein anderes Ziel gewaehlt: der Zielwert
     * richtet sich neu aus. Eine Szene braucht keinen -- sie wird aufgerufen.
     */
    public function ZeileZiel(int $TargetID): void
    {
        $variable = $this->Zielvariable($TargetID);

        $this->UpdateFormField('Value', 'variableID', $variable);
        $this->UpdateFormField('Value', 'visible', $variable > 0);
        if ($variable > 0) {
            $this->UpdateFormField('Value', 'value', GetValue($variable));
        }
    }

    /**
     * Nach dem Bearbeiten einer Zeile: die Anzeigespalten neu schreiben.
     */
    public function Nachbeschriften(string $Liste): void
    {
        $zeilen = json_decode($Liste, true);
        $this->UpdateFormField('Points', 'values', json_encode($this->Beschriftet(is_array($zeilen) ? $zeilen : [])));
    }

    // ------------------------------------------------------------------
    // Ablauf
    // ------------------------------------------------------------------

    /**
     * Der Wecker: schaltet, was seit dem letzten Lauf faellig geworden ist,
     * und stellt sich neu.
     */
    public function Feuern(): void
    {
        $jetzt = time();
        $seit = $this->ReadAttributeInteger('LastRun');
        if (($seit <= 0) || ($seit > $jetzt)) {
            $seit = $jetzt - 60;
        }

        if ($this->GetValue('Aktiv')) {
            foreach ($this->Punkte() as $index => $punkt) {
                if (!($punkt['Active'] ?? true)) {
                    continue;
                }
                foreach ($this->Termine($punkt, $index, $seit, $jetzt) as $termin) {
                    $this->SendDebug('Zeitschaltuhr', 'Punkt ' . $index . ' faellig um ' . date('d.m. H:i:s', $termin), 0);
                    $this->Ausloesen($index);
                    break;
                }
            }
        }

        $this->WriteAttributeInteger('LastRun', $jetzt);
        $this->Planen();
    }

    /**
     * Stellt den Wecker auf den naechsten Termin und schreibt ihn an.
     */
    public function Planen(): void
    {
        $naechster = $this->NaechsterTermin(time());

        if ($naechster === null) {
            $this->SetTimerInterval('Schalten', 0);
        } else {
            $vorlauf = max(1, min($naechster['zeit'] - time(), self::VORLAUF_MAX));
            $this->SetTimerInterval('Schalten', $vorlauf * 1000);
        }

        $text = $this->NaechsteSchaltung();
        $this->SetValue('Naechste', $text);

        $this->RegoPush('Aktiv', $this->GetValue('Aktiv'));
        $this->RegoPush('Felder', $this->Kachelfelder($naechster));
        $this->RegoPush('Skip', ($naechster !== null) && $this->IstUebersprungen($naechster['index'], $naechster['zeit']));
    }

    /**
     * Der naechste Termin als Text -- auch fuer Skripte und Meldungen.
     */
    public function NaechsteSchaltung(): string
    {
        if (!$this->GetValue('Aktiv')) {
            return 'Uhr ist aus';
        }

        $naechster = $this->NaechsterTermin(time());
        if ($naechster === null) {
            return 'kein Termin';
        }

        return $this->Zeitpunkttext($naechster['zeit']);
    }

    /**
     * Den naechsten Termin einmalig auslassen -- und beim zweiten Aufruf
     * wieder einsetzen. Die Vorlage macht es genauso: die Existenz des
     * Eintrags ist das Ueberspringen, sein Wegfall die Ruecknahme.
     */
    public function Ueberspringen(): bool
    {
        $naechster = $this->NaechsterTermin(time(), true);
        if ($naechster === null) {
            return false;
        }

        $skips = $this->Skips();
        $schluessel = (string) $naechster['index'];
        $tag = date('Y-m-d', $naechster['zeit']);

        if (($skips[$schluessel] ?? '') === $tag) {
            unset($skips[$schluessel]);
            $uebersprungen = false;
        } else {
            $skips[$schluessel] = $tag;
            $uebersprungen = true;
        }

        $this->WriteAttributeString('Skips', json_encode($skips));
        $this->Planen();

        return $uebersprungen;
    }

    /**
     * Schaltet einen Punkt sofort, unabhaengig von seiner Zeit. Fuer
     * Ereignisse, Skripte und zum Ausprobieren.
     */
    public function Ausloesen(int $Index): bool
    {
        $punkte = $this->Punkte();
        if (!isset($punkte[$Index])) {
            return false;
        }

        $ziel = (int) ($punkte[$Index]['TargetID'] ?? 0);
        if (($ziel == 0) || !IPS_ObjectExists($ziel)) {
            $this->SendDebug('Zeitschaltuhr', 'Punkt ' . $Index . ' hat kein Ziel', 0);
            return false;
        }

        if ($this->IstSzene($ziel)) {
            IPS_RequestAction($ziel, 'Apply', true);
            $this->SendDebug('Zeitschaltuhr', 'Szene ' . IPS_GetName($ziel) . ' aufgerufen', 0);
            return true;
        }

        $variable = $this->Zielvariable($ziel);
        if ($variable == 0) {
            $this->SendDebug('Zeitschaltuhr', 'Punkt ' . $Index . ': ' . IPS_GetName($ziel) . ' hat keine bedienbare Variable', 0);
            return false;
        }

        return $this->RegoWrite($variable, $this->RegoZielwert($variable, $punkte[$Index]['Value'] ?? ''));
    }

    // ------------------------------------------------------------------
    // Zeitrechnung
    // ------------------------------------------------------------------

    /**
     * Der naechste faellige Termin ueber alle Punkte.
     *
     * $auchUebersprungene liefert auch den Termin zurueck, der gerade
     * ausgelassen wird -- die Kachel braucht ihn, um das Ueberspringen wieder
     * zuruecknehmen zu koennen.
     */
    private function NaechsterTermin(int $ab, bool $auchUebersprungene = false): ?array
    {
        if (!$this->GetValue('Aktiv')) {
            return null;
        }

        $bestes = null;

        foreach ($this->Punkte() as $index => $punkt) {
            if (!($punkt['Active'] ?? true)) {
                continue;
            }

            $tag = strtotime('today', $ab);
            // Acht Tage reichen: bei einer Wochenuhr kommt jeder aktive Tag
            // darin mindestens einmal vor.
            for ($i = 0; $i <= 8; $i++) {
                $zeit = $this->Zeitpunkt($punkt, $tag);
                if (($zeit !== null) && ($zeit > $ab) && $this->AmTag($punkt, $tag)) {
                    if ($auchUebersprungene || !$this->IstUebersprungen($index, $zeit)) {
                        if (($bestes === null) || ($zeit < $bestes['zeit'])) {
                            $bestes = ['zeit' => $zeit, 'index' => $index];
                        }
                        break;
                    }
                }
                $tag = strtotime('+1 day', $tag);
            }
        }

        return $bestes;
    }

    /**
     * Alle Termine eines Punktes im Fenster (von, bis].
     */
    private function Termine(array $punkt, int $index, int $von, int $bis): array
    {
        $treffer = [];

        $tag = strtotime('today', $von);
        while ($tag <= $bis) {
            $zeit = $this->Zeitpunkt($punkt, $tag);
            if (($zeit !== null) && ($zeit > $von) && ($zeit <= $bis)
                && $this->AmTag($punkt, $tag) && !$this->IstUebersprungen($index, $zeit)) {
                $treffer[] = $zeit;
            }
            $tag = strtotime('+1 day', $tag);
        }

        return $treffer;
    }

    /**
     * Der Zeitpunkt eines Punktes an einem bestimmten Tag.
     *
     * Nicht $tagesbeginn + Sekunden: an den Umstellungstagen hat ein Tag 23
     * oder 25 Stunden, mktime() trifft die Uhrzeit trotzdem.
     */
    private function Zeitpunkt(array $punkt, int $tagesbeginn): ?int
    {
        $art = (int) ($punkt['TimeType'] ?? 0);

        if ($art != 0) {
            list($breite, $laenge) = $this->Standort();
            $sonne = date_sun_info($tagesbeginn + 43200, $breite, $laenge);
            $wert = ($art == 1) ? $sonne['sunrise'] : $sonne['sunset'];
            if (!is_int($wert) && !is_float($wert)) {
                // Polartag oder Polarnacht -- an diesem Tag gibt es den
                // Zeitpunkt schlicht nicht.
                return null;
            }

            return ((int) $wert) + ((int) ($punkt['Offset'] ?? 0)) * 60;
        }

        $zeit = json_decode((string) ($punkt['Time'] ?? ''), true);

        return mktime(
            (int) ($zeit['hour'] ?? 0),
            (int) ($zeit['minute'] ?? 0),
            (int) ($zeit['second'] ?? 0),
            (int) date('n', $tagesbeginn),
            (int) date('j', $tagesbeginn),
            (int) date('Y', $tagesbeginn)
        );
    }

    /**
     * Gilt der Punkt an diesem Tag? Eine Tagesuhr kennt keine Wochentage.
     */
    private function AmTag(array $punkt, int $tagesbeginn): bool
    {
        if ($this->ReadPropertyInteger('Mode') == 0) {
            return true;
        }

        return (bool) ($punkt['D' . ((int) date('N', $tagesbeginn))] ?? false);
    }

    private function IstUebersprungen(int $index, int $zeit): bool
    {
        return ($this->Skips()[(string) $index] ?? '') === date('Y-m-d', $zeit);
    }

    /**
     * Breite und Laenge fuer die Sonnenzeiten.
     *
     * Erst die eingetragene Standort-Instanz, sonst die von Symcon, sonst
     * Berlin -- dieselbe Vorgabe wie in der Vorlage.
     */
    private function Standort(): array
    {
        $id = $this->ReadPropertyInteger('LocationInstance');
        if (($id == 0) || !IPS_InstanceExists($id)) {
            $id = IPS_GetInstanceListByModuleID(self::STANDORT_GUID)[0] ?? 0;
        }

        if ($id > 0) {
            $ort = json_decode((string) (json_decode(IPS_GetConfiguration($id), true)['Location'] ?? ''), true);
            if (isset($ort['latitude'], $ort['longitude'])) {
                return [(float) $ort['latitude'], (float) $ort['longitude']];
            }
        }

        return [52.520008, 13.404954];
    }

    // ------------------------------------------------------------------
    // Ziele
    // ------------------------------------------------------------------

    private function IstSzene(int $objektID): bool
    {
        return IPS_InstanceExists($objektID)
            && (IPS_GetInstance($objektID)['ModuleInfo']['ModuleID'] === self::SZENE_GUID);
    }

    /**
     * Die Variable hinter einem Ziel.
     *
     * Eine Variable ist ihre eigene. Bei einer Instanz zaehlt die Variable,
     * die sich bedienen laesst -- ein KNX-Geraet hat genau eine. Gibt es
     * mehrere, bleibt es beim "Value", sonst bei nichts: raten waere hier
     * schlimmer als nachfragen.
     */
    private function Zielvariable(int $objektID): int
    {
        if (($objektID == 0) || !IPS_ObjectExists($objektID)) {
            return 0;
        }
        if (IPS_VariableExists($objektID)) {
            return $objektID;
        }
        if (!IPS_InstanceExists($objektID) || $this->IstSzene($objektID)) {
            return 0;
        }

        $modul = IPS_GetInstance($objektID)['ModuleInfo']['ModuleID'];
        if (isset(self::KACHEL_ZIEL[$modul])) {
            $variable = (int) (json_decode(IPS_GetConfiguration($objektID), true)[self::KACHEL_ZIEL[$modul]] ?? 0);

            return IPS_VariableExists($variable) ? $variable : 0;
        }

        $bedienbar = [];
        foreach (IPS_GetChildrenIDs($objektID) as $kind) {
            if (!IPS_VariableExists($kind)) {
                continue;
            }
            $variable = IPS_GetVariable($kind);
            if (($variable['VariableAction'] != 0) || ($variable['VariableCustomAction'] > 1)) {
                $bedienbar[] = $kind;
            }
        }

        if (count($bedienbar) === 1) {
            return $bedienbar[0];
        }

        $value = @IPS_GetObjectIDByIdent('Value', $objektID);

        return ($value === false) ? 0 : $value;
    }

    // ------------------------------------------------------------------
    // Anzeige
    // ------------------------------------------------------------------

    public function GetVisualizationTile(): string
    {
        $naechster = $this->NaechsterTermin(time(), true);

        $inner = $this->RegoFelder($this->Kachelfelder($naechster))
            . $this->RegoButtons(
                '<button type="button" id="rego-onoff" class="onoff-button onoff-button-unknown" '
                . 'onclick="regoSchalten()">unbekannt</button>'
                . '<button type="button" id="rego-skip" onclick="requestAction(\'Skip\', true)">Überspringen</button>'
            );

        $script = $this->RegoFelderScript() . <<<'JS'
function regoSchalten() {
    requestAction('Aktiv', window.regoState.Aktiv !== true);
}
function regoRenderAktiv(aktiv) {
    window.regoState.Aktiv = aktiv;
    var knopf = document.getElementById('rego-onoff');
    knopf.className = 'onoff-button ' + (aktiv === true ? 'onoff-button-on' : 'onoff-button-off');
    knopf.textContent = aktiv === true ? 'AN' : 'AUS';
}
function regoRenderSkip(uebersprungen) {
    window.regoState.Skip = uebersprungen;
    var knopf = document.getElementById('rego-skip');
    knopf.textContent = uebersprungen === true ? 'Doch schalten' : 'Überspringen';
    knopf.classList.toggle('szene-aktiv', uebersprungen === true);
}
window.regoHandlers['Aktiv'] = regoRenderAktiv;
window.regoHandlers['Skip'] = regoRenderSkip;
regoRenderAktiv(window.regoState.Aktiv);
regoRenderSkip(window.regoState.Skip);
JS;

        return $this->RegoTile('zeitschaltuhr', $inner, $script, [
            'Aktiv'  => $this->GetValue('Aktiv'),
            'Felder' => $this->Kachelfelder($naechster),
            'Skip'   => ($naechster !== null) && $this->IstUebersprungen($naechster['index'], $naechster['zeit']),
        ]);
    }

    /**
     * Was die Kachel zeigt: wann als naechstes geschaltet wird und worauf.
     */
    private function Kachelfelder(?array $naechster): array
    {
        if (!$this->GetValue('Aktiv')) {
            return [[['label' => 'Nächste Schaltung', 'text' => 'Uhr ist aus']]];
        }
        if ($naechster === null) {
            return [[['label' => 'Nächste Schaltung', 'text' => 'kein Termin']]];
        }

        $punkte = $this->Punkte();
        $punkt = $punkte[$naechster['index']] ?? [];
        $zeit = $this->Zeitpunkttext($naechster['zeit']);
        if ($this->IstUebersprungen($naechster['index'], $naechster['zeit'])) {
            $zeit = 'ausgelassen, dann ' . $this->Zeitpunkttext(
                $this->NaechsterTermin($naechster['zeit'])['zeit'] ?? $naechster['zeit']
            );
        }

        return [[
            ['label' => 'Nächste Schaltung', 'text' => $zeit],
            ['label' => 'Ziel', 'text' => $this->Zieltext($punkt)],
        ]];
    }

    /**
     * "heute 20:34", "morgen 06:29", sonst mit Datum.
     */
    private function Zeitpunkttext(int $zeit): string
    {
        $tag = date('Y-m-d', $zeit);
        if ($tag === date('Y-m-d')) {
            return 'heute ' . date('H:i', $zeit);
        }
        if ($tag === date('Y-m-d', strtotime('+1 day'))) {
            return 'morgen ' . date('H:i', $zeit);
        }

        return self::TAGE[(int) date('N', $zeit)] . ' ' . date('d.m. H:i', $zeit);
    }

    /**
     * Die Liste mit frisch beschrifteten Anzeigespalten.
     */
    private function Beschriftet(array $punkte): array
    {
        foreach ($punkte as $index => $punkt) {
            $punkte[$index]['Tage'] = $this->Tagetext($punkt);
            $punkte[$index]['Zeit'] = $this->Zeittext($punkt);
            $punkte[$index]['Ziel'] = $this->Zieltext($punkt);
        }

        return $punkte;
    }

    private function Tagetext(array $punkt): string
    {
        if ($this->ReadPropertyInteger('Mode') == 0) {
            return 'täglich';
        }

        $tage = [];
        foreach (self::TAGE as $nummer => $kuerzel) {
            if ($punkt['D' . $nummer] ?? false) {
                $tage[] = $kuerzel;
            }
        }

        if (count($tage) === 7) {
            return 'täglich';
        }

        return empty($tage) ? 'nie' : implode(' ', $tage);
    }

    private function Zeittext(array $punkt): string
    {
        $art = (int) ($punkt['TimeType'] ?? 0);

        if ($art != 0) {
            $name = ($art == 1) ? 'Sonnenaufgang' : 'Sonnenuntergang';
            $offset = (int) ($punkt['Offset'] ?? 0);
            if ($offset !== 0) {
                $name .= ($offset > 0 ? ' + ' : ' − ') . abs($offset) . ' min';
            }
            $zeit = $this->Zeitpunkt($punkt, strtotime('today'));

            return $name . (($zeit === null) ? '' : ' (' . date('H:i', $zeit) . ')');
        }

        $zeit = json_decode((string) ($punkt['Time'] ?? ''), true);

        return sprintf('%02d:%02d', (int) ($zeit['hour'] ?? 0), (int) ($zeit['minute'] ?? 0));
    }

    private function Zieltext(array $punkt): string
    {
        $ziel = (int) ($punkt['TargetID'] ?? 0);
        if (($ziel == 0) || !IPS_ObjectExists($ziel)) {
            return 'kein Ziel';
        }
        if ($this->IstSzene($ziel)) {
            return IPS_GetName($ziel) . ' aufrufen';
        }

        $variable = $this->Zielvariable($ziel);
        if ($variable == 0) {
            return IPS_GetName($ziel) . ' — keine bedienbare Variable';
        }

        return $this->Zielname($ziel, $variable) . ' → '
            . $this->RegoWertText($variable, $this->RegoZielwert($variable, $punkt['Value'] ?? ''));
    }

    /**
     * "Value" sagt niemandem etwas -- dann nennt die Anzeige das Geraet, dem
     * die Variable gehoert.
     */
    private function Zielname(int $objektID, int $variable): string
    {
        $name = IPS_GetName($objektID);
        if (($objektID !== $variable) || !in_array($name, ['Value', 'Wert'], true)) {
            return $name;
        }

        $eltern = IPS_GetObject($variable)['ParentID'];

        return ($eltern > 0) ? IPS_GetName($eltern) : $name;
    }

    private function Punkte(): array
    {
        $liste = json_decode($this->ReadPropertyString('Points'), true);

        return is_array($liste) ? array_values($liste) : [];
    }

    private function Skips(): array
    {
        $liste = json_decode($this->ReadAttributeString('Skips'), true);

        return is_array($liste) ? $liste : [];
    }
}
