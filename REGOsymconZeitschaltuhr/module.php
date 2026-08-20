<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/RegoSymconTile.php';

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
class REGOsymconZeitschaltuhr extends IPSModule
{
    use RegoSymconTile;

    private const TAGE = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    private const STANDORT_GUID = '{45E97A63-F870-408A-B259-2933F7EABF74}';
    private const SZENE_GUID = '{458A32A4-E74F-45D1-89D3-C31EFC98FEB2}';

    // Zeigt das Ziel auf eine REGOsymcon-Kachel, ist die Variable gemeint, die
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
        $this->RegisterPropertyBoolean('EditInTile', false);

        $this->RegisterAttributeInteger('LastRun', 0);
        $this->RegisterAttributeString('Skips', '{}');

        $this->RegisterTimer('Schalten', 0, 'RGSZU_Feuern($_IPS[\'TARGET\']);');

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

            case 'Punkt':
                $this->PunktAendern((string) $Value);
                break;

            case 'Neu':
                $this->PunktAnlegen();
                break;

            case 'Art':
                if ($this->ReadPropertyBoolean('EditInTile')) {
                    IPS_SetProperty($this->InstanceID, 'Mode', $Value ? 1 : 0);
                    IPS_ApplyChanges($this->InstanceID);
                }
                break;
        }
    }

    /**
     * Eine Aenderung aus der Kachel: Uhrzeit, ein Wochentag oder der Schalter
     * eines Punktes. Das Ziel bleibt aussen vor -- dafuer braucht es den
     * Objektbaum der Konsole.
     */
    private function PunktAendern(string $auftrag): void
    {
        if (!$this->ReadPropertyBoolean('EditInTile')) {
            return;
        }

        $daten = json_decode($auftrag, true);
        if (!is_array($daten)) {
            return;
        }

        $punkte = $this->Punkte();
        $index = (int) ($daten['index'] ?? -1);
        if (!isset($punkte[$index])) {
            return;
        }

        switch ($daten['feld'] ?? '') {
            case 'aktiv':
                $punkte[$index]['Active'] = (bool) ($daten['wert'] ?? false);
                break;

            case 'zeit':
                // Das Zeitfeld des Browsers liefert "HH:MM", manchmal mit
                // Sekunden. Alles andere wird abgewiesen.
                if (!preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', (string) ($daten['wert'] ?? ''), $treffer)) {
                    return;
                }
                $punkte[$index]['Time'] = json_encode([
                    'hour'   => min(23, (int) $treffer[1]),
                    'minute' => min(59, (int) $treffer[2]),
                    'second' => min(59, (int) ($treffer[4] ?? 0)),
                ]);
                break;

            case 'tag':
                $tag = (int) ($daten['wert'] ?? 0);
                if (($tag < 1) || ($tag > 7)) {
                    return;
                }
                $punkte[$index]['D' . $tag] = !($punkte[$index]['D' . $tag] ?? false);
                break;

            case 'ziel':
                $ziel = (int) ($daten['wert'] ?? 0);
                if (($ziel != 0) && !IPS_ObjectExists($ziel)) {
                    return;
                }
                $punkte[$index]['TargetID'] = $ziel;
                // Der alte Zielwert passt selten zum neuen Ziel -- der
                // aktuelle Zustand ist der bessere Anfang.
                $variable = $this->IstSzene($ziel) ? 0 : $this->Zielvariable($ziel);
                $punkte[$index]['Value'] = ($variable > 0) ? json_encode(GetValue($variable)) : 'null';
                break;

            case 'wert':
                $roh = (string) ($daten['wert'] ?? 'null');
                if ((json_decode($roh, true) === null) && (trim($roh) !== 'null')) {
                    return;
                }
                $punkte[$index]['Value'] = $roh;
                break;

            case 'zeitart':
                $art = (int) ($daten['wert'] ?? 0);
                if (($art < 0) || ($art > 2)) {
                    return;
                }
                $punkte[$index]['TimeType'] = $art;
                break;

            case 'verschiebung':
                $punkte[$index]['Offset'] = max(-720, min(720, (int) ($daten['wert'] ?? 0)));
                break;

            case 'weg':
                unset($punkte[$index]);
                $punkte = array_values($punkte);
                break;

            default:
                return;
        }

        IPS_SetProperty($this->InstanceID, 'Points', json_encode($punkte));
        IPS_ApplyChanges($this->InstanceID);
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
     *
     * Die Wochentage stehen nur bei einer Wochenuhr da. Eine Tagesuhr schaltet
     * jeden Tag; ein Haekchen, das nichts bewirkt, verwirrt nur. $Mode kommt
     * aus dem geoeffneten Formular, nicht aus der Eigenschaft -- so stimmt es
     * auch, wenn die Art gerade umgestellt und noch nicht uebernommen wurde.
     */
    public function Zeilenformular(int $TargetID, int $TimeType, int $Mode = 1): string
    {
        $variable = $this->Zielvariable($TargetID);
        $astro = ($TimeType != 0);

        $elemente = [
            [
                'type'    => 'CheckBox',
                'name'    => 'Active',
                'caption' => 'Aktiv',
            ],
        ];

        if ($Mode == 1) {
            $wochentage = [];
            foreach (self::TAGE as $nummer => $kuerzel) {
                $wochentage[] = [
                    'type'    => 'CheckBox',
                    'name'    => 'D' . $nummer,
                    'caption' => $kuerzel,
                ];
            }
            $elemente[] = [
                'type'  => 'RowLayout',
                'items' => $wochentage,
            ];
        }

        return json_encode(array_merge($elemente, [
            [
                'type'    => 'Select',
                'name'    => 'TimeType',
                'caption' => 'Zeitpunkt',
                'options' => [
                    ['caption' => 'Feste Uhrzeit', 'value' => 0],
                    ['caption' => 'Sonnenaufgang', 'value' => 1],
                    ['caption' => 'Sonnenuntergang', 'value' => 2],
                ],
                'onChange' => 'RGSZU_ZeileZeitart($id, $TimeType);',
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
                'onChange' => 'RGSZU_ZeileZiel($id, $TargetID);',
            ],
            [
                'type'       => 'SelectValue',
                'name'       => 'Value',
                'caption'    => 'Zielwert',
                'variableID' => $variable,
                'visible'    => ($variable > 0),
            ],
        ]));
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
        if ($this->ReadPropertyBoolean('EditInTile')) {
            $this->RegoPush('Ziele', $this->Ziele());
            $this->RegoPush('Wochenuhr', $this->ReadPropertyInteger('Mode') == 1);
            $this->RegoPush('Punkte', $this->Kachelpunkte());
        }
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

        $bedienbar = $this->ReadPropertyBoolean('EditInTile');

        $inner = $this->RegoFelder($this->Kachelfelder($naechster))
            . ($bedienbar
                ? '<div class="punkte" id="rego-punkte"></div>'
                  . '<div class="waehler" id="rego-waehler" hidden>'
                  . '<div class="waehler-kopf"><span id="rego-waehler-titel"></span></div>'
                  . '<div class="waehler-liste" id="rego-waehler-liste"></div>'
                  . '<button type="button" class="waehler-zu" onclick="regoWahlZu()">Fertig</button>'
                  . '</div>'
                  . '<div class="punkt-fuss">'
                  . '<button type="button" class="punkt-neu" onclick="requestAction(\'Neu\', true)">+ Schaltpunkt</button>'
                  . '<button type="button" id="rego-art" onclick="regoArt()"></button>'
                  . '</div>'
                : '')
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
function regoAendern(index, feld, wert) {
    requestAction('Punkt', JSON.stringify({index: index, feld: feld, wert: wert}));
}
function regoWahlZu() {
    document.getElementById('rego-waehler').hidden = true;
}
// Auswahl ueber Knoepfe statt ueber ein select: das Auswahlfeld des Browsers
// laesst sich im Rahmen der Kachel nicht aufklappen, Knoepfe funktionieren.
function regoWahlAuf(titel, optionen, wert, beim) {
    var blatt = document.getElementById('rego-waehler');
    var liste = document.getElementById('rego-waehler-liste');
    document.getElementById('rego-waehler-titel').textContent = titel;
    liste.innerHTML = '';

    optionen.forEach(function (o) {
        var knopf = document.createElement('button');
        knopf.type = 'button';
        knopf.className = 'waehler-eintrag' + (String(o.wert) === String(wert) ? ' waehler-hier' : '');
        knopf.textContent = o.text;
        knopf.onclick = function () {
            regoWahlZu();
            beim(o.wert);
        };
        liste.appendChild(knopf);
    });

    blatt.hidden = false;
    liste.scrollTop = 0;
}
// Uhrzeit: Stunde und Minute in zwei Spalten, jede Minute einzeln. Ein
// Viertelstundenraster waere kuerzer, aber eine Zeitschaltuhr, die 06:35 nicht
// kann, ist keine.
function regoZeitAuf(zeit, beim) {
    var teile = String(zeit || '00:00').split(':');
    var stand = {stunde: Number(teile[0]) || 0, minute: Number(teile[1]) || 0};

    var blatt = document.getElementById('rego-waehler');
    var liste = document.getElementById('rego-waehler-liste');
    document.getElementById('rego-waehler-titel').textContent = 'Uhrzeit';

    var springen = true;
    var zeichnen = function () {
        liste.innerHTML = '';
        var spalten = document.createElement('div');
        spalten.className = 'waehler-spalten';

        [['Stunde', 24, 'stunde'], ['Minute', 60, 'minute']].forEach(function (spalte) {
            var kasten = document.createElement('div');
            kasten.className = 'waehler-spalte';

            var kopf = document.createElement('span');
            kopf.className = 'waehler-spalte-kopf';
            kopf.textContent = spalte[0];
            kasten.appendChild(kopf);

            for (var i = 0; i < spalte[1]; i++) {
                (function (wert) {
                    var knopf = document.createElement('button');
                    knopf.type = 'button';
                    knopf.className = 'waehler-eintrag waehler-zahl'
                        + (stand[spalte[2]] === wert ? ' waehler-hier' : '');
                    knopf.textContent = ('0' + wert).slice(-2);
                    knopf.onclick = function () {
                        stand[spalte[2]] = wert;
                        beim(('0' + stand.stunde).slice(-2) + ':' + ('0' + stand.minute).slice(-2));
                        zeichnen();
                    };
                    kasten.appendChild(knopf);
                })(i);
            }

            spalten.appendChild(kasten);
        });

        liste.appendChild(spalten);

        if (springen) {
            // Die gewaehlte Zahl in die Mitte ihrer Spalte ruecken. offsetTop
            // zaehlt schon ab der Spalte, die ist selbst positioniert.
            Array.prototype.forEach.call(liste.querySelectorAll('.waehler-hier'), function (k) {
                var spalte = k.parentNode;
                spalte.scrollTop = k.offsetTop - (spalte.clientHeight / 2) + (k.offsetHeight / 2);
            });
        }
    };

    // Nur beim Oeffnen springen: nach einem Klick steht die Zahl ohnehin unter
    // dem Finger, ein Sprung waere dort nur ein Zucken.
    zeichnen();
    springen = false;
    blatt.hidden = false;
}
function regoWaehler(klasse, titel, optionen, wert, beim) {
    var knopf = document.createElement('button');
    knopf.type = 'button';
    knopf.className = 'waehler-knopf ' + klasse;

    var gewaehlt = null;
    optionen.forEach(function (o) {
        if (String(o.wert) === String(wert)) {
            gewaehlt = o;
        }
    });
    knopf.textContent = gewaehlt ? gewaehlt.text : (optionen.length ? optionen[0].text : '—');
    knopf.title = titel;
    knopf.onclick = function () { regoWahlAuf(titel, optionen, wert, beim); };

    return knopf;
}
function regoArt() {
    requestAction('Art', window.regoState.Wochenuhr !== true);
}
function regoRenderPunkte(punkte) {
    window.regoState.Punkte = punkte;
    var liste = document.getElementById('rego-punkte');
    if (!liste) {
        return;
    }
    liste.innerHTML = '';

    var knopf = document.getElementById('rego-art');
    if (knopf) {
        knopf.textContent = window.regoState.Wochenuhr ? 'Wochenuhr' : 'Tagesuhr';
    }

    (punkte || []).forEach(function (punkt) {
        var zeile = document.createElement('div');
        zeile.className = 'punkt' + (punkt.aktiv ? '' : ' punkt-aus');

        // Zwei Reihen: oben wann, unten was. Alles nebeneinander quetscht in
        // einer schmalen Kachel die Ziel-Auswahl auf null Breite zusammen.
        var oben = document.createElement('div');
        oben.className = 'punkt-reihe';
        var unten = document.createElement('div');
        unten.className = 'punkt-reihe';

        var schalter = document.createElement('button');
        schalter.type = 'button';
        schalter.className = 'punkt-an' + (punkt.aktiv ? ' punkt-an-ein' : '');
        schalter.title = punkt.aktiv ? 'Punkt aussetzen' : 'Punkt einschalten';
        schalter.onclick = function () { regoAendern(punkt.index, 'aktiv', !punkt.aktiv); };
        oben.appendChild(schalter);

        oben.appendChild(regoWaehler('punkt-art', 'Zeitpunkt', [
            {wert: 0, text: 'Uhr'}, {wert: 1, text: 'Sonnenaufgang'}, {wert: 2, text: 'Sonnenuntergang'}
        ], punkt.zeitart, function (v) { regoAendern(punkt.index, 'zeitart', Number(v)); }));

        if (punkt.zeitart) {
            var stufen = [];
            for (var m = -120; m <= 120; m++) {
                stufen.push({wert: m, text: (m > 0 ? '+' : '') + m + ' min'});
            }
            oben.appendChild(regoWaehler('punkt-zeit punkt-offset', 'Verschiebung', stufen, punkt.verschiebung,
                function (v) { regoAendern(punkt.index, 'verschiebung', Number(v)); }));
        } else {
            var uhrzeit = document.createElement('button');
            uhrzeit.type = 'button';
            uhrzeit.className = 'waehler-knopf punkt-zeit';
            uhrzeit.textContent = punkt.zeit;
            uhrzeit.title = 'Uhrzeit';
            uhrzeit.onclick = function () {
                regoZeitAuf(punkt.zeit, function (v) { regoAendern(punkt.index, 'zeit', v); });
            };
            oben.appendChild(uhrzeit);
        }

        if (punkt.tage) {
            var tage = document.createElement('span');
            tage.className = 'punkt-tage';
            punkt.tage.forEach(function (tag, i) {
                var t = document.createElement('button');
                t.type = 'button';
                t.className = 'tag' + (tag.an ? ' tag-an' : '');
                t.textContent = tag.kurz;
                t.onclick = function () { regoAendern(punkt.index, 'tag', i + 1); };
                tage.appendChild(t);
            });
            oben.appendChild(tage);
        }

        var luecke = document.createElement('span');
        luecke.className = 'punkt-luecke';
        oben.appendChild(luecke);

        var weg = document.createElement('button');
        weg.type = 'button';
        weg.className = 'punkt-weg';
        weg.title = 'Schaltpunkt löschen';
        weg.textContent = '×';
        weg.onclick = function () { regoAendern(punkt.index, 'weg', true); };
        oben.appendChild(weg);

        var ziele = [{wert: 0, text: '— Ziel wählen —'}].concat(window.regoState.Ziele || []);
        unten.appendChild(regoWaehler('punkt-zielwahl', 'Ziel', ziele, punkt.ziel,
            function (v) { regoAendern(punkt.index, 'ziel', Number(v)); }));

        var feld = punkt.wertfeld || {typ: 'keins'};
        if (feld.typ === 'auswahl') {
            unten.appendChild(regoWaehler('punkt-wert', 'Zielwert', feld.optionen, punkt.wert,
                function (v) { regoAendern(punkt.index, 'wert', v); }));
        } else if (feld.typ === 'zahl') {
            var jetzt = 0;
            try { jetzt = Number(JSON.parse(punkt.wert)) || 0; } catch (e) { jetzt = feld.min; }
            var stufen = [];
            for (var w = feld.min; w <= feld.max; w += Math.max(feld.schritt, (feld.max - feld.min) / 20)) {
                stufen.push({wert: JSON.stringify(Math.round(w * 100) / 100),
                             text: (Math.round(w * 100) / 100) + (feld.einheit ? ' ' + feld.einheit : '')});
            }
            unten.appendChild(regoWaehler('punkt-wert', 'Zielwert', stufen, JSON.stringify(jetzt),
                function (v) { regoAendern(punkt.index, 'wert', v); }));
        } else if (feld.typ !== 'keins') {
            var text = document.createElement('span');
            text.className = 'punkt-wert-leer';
            text.textContent = punkt.wert;
            unten.appendChild(text);
        }

        zeile.appendChild(oben);
        zeile.appendChild(unten);
        liste.appendChild(zeile);
    });

    if (!(punkte || []).length) {
        var hinweis = document.createElement('span');
        hinweis.className = 'punkt-leer';
        hinweis.textContent = 'Noch kein Schaltpunkt angelegt';
        liste.appendChild(hinweis);
    }
}
window.regoHandlers['Aktiv'] = regoRenderAktiv;
window.regoHandlers['Skip'] = regoRenderSkip;
regoRenderAktiv(window.regoState.Aktiv);
regoRenderSkip(window.regoState.Skip);
if (document.getElementById('rego-punkte')) {
    // Ziele und Art kommen vor den Punkten an -- gezeichnet wird erst mit
    // den Punkten, sonst blinkt die Liste bei jeder Aenderung dreimal.
    window.regoHandlers['Ziele'] = function (ziele) { window.regoState.Ziele = ziele; };
    window.regoHandlers['Wochenuhr'] = function (an) { window.regoState.Wochenuhr = an; };
    window.regoHandlers['Punkte'] = regoRenderPunkte;
    regoRenderPunkte(window.regoState.Punkte);
}
JS;

        return $this->RegoTile('zeitschaltuhr', $inner, $script, [
            'Aktiv'  => $this->GetValue('Aktiv'),
            'Felder' => $this->Kachelfelder($naechster),
            'Skip'   => ($naechster !== null) && $this->IstUebersprungen($naechster['index'], $naechster['zeit']),
            'Punkte' => $bedienbar ? $this->Kachelpunkte() : [],
            'Ziele'  => $bedienbar ? $this->Ziele() : [],
            'Wochenuhr' => ($this->ReadPropertyInteger('Mode') == 1),
        ]);
    }

    /**
     * Ein neuer Schaltpunkt aus der Kachel: 18:00, alle Tage, noch ohne Ziel.
     * Ohne Ziel schaltet er nichts -- das faellt in der Liste sofort auf und
     * ist ehrlicher, als irgendetwas zu raten.
     */
    private function PunktAnlegen(): void
    {
        if (!$this->ReadPropertyBoolean('EditInTile')) {
            return;
        }

        $punkte = $this->Punkte();
        $punkte[] = [
            'Active' => true, 'Tage' => '', 'Zeit' => '', 'Ziel' => '',
            'D1' => true, 'D2' => true, 'D3' => true, 'D4' => true,
            'D5' => true, 'D6' => true, 'D7' => true,
            'TimeType' => 0, 'Time' => json_encode(['hour' => 18, 'minute' => 0, 'second' => 0]),
            'Offset' => 0, 'TargetID' => 0, 'Value' => 'null',
        ];

        IPS_SetProperty($this->InstanceID, 'Points', json_encode($punkte));
        IPS_ApplyChanges($this->InstanceID);
    }

    /**
     * Was die Kachel als Ziel anbietet.
     *
     * Den Objektbaum kann eine Kachel nicht oeffnen, also stellt sie eine
     * Liste: alles, was REGOsymcon in den Raeumen angelegt hat, plus die
     * Szenen. Der Raum steht davor, sonst heisst die Haelfte "Deckenlicht".
     */
    private function Ziele(): array
    {
        $ziele = [];

        foreach (array_merge(array_keys(self::KACHEL_ZIEL), [self::SZENE_GUID]) as $modul) {
            foreach (@IPS_GetInstanceListByModuleID($modul) ?: [] as $id) {
                if (($modul !== self::SZENE_GUID) && ($this->Zielvariable($id) == 0)) {
                    continue;
                }
                $ordner = IPS_GetObject($id)['ParentID'];
                // "wert"/"text" wie bei jeder anderen Auswahl der Kachel --
                // eine eigene Schreibweise hier hiesse, dass jede Option den
                // Wert "undefined" bekommt und sich nichts auswaehlen laesst.
                $ziele[] = [
                    'wert' => $id,
                    'text' => (($ordner > 0) ? IPS_GetName($ordner) . ' · ' : '') . IPS_GetName($id),
                ];
            }
        }

        usort($ziele, function ($a, $b) {
            return strcasecmp($a['text'], $b['text']);
        });

        return $ziele;
    }

    /**
     * Das passende Bedienelement fuer den Zielwert -- abgeleitet aus dem
     * Profil der Zielvariable, damit in der Kachel "An" steht und nicht 1.
     */
    private function Wertfeld(int $variableID): array
    {
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            return ['typ' => 'keins'];
        }

        $variable = IPS_GetVariable($variableID);
        $profil = $variable['VariableCustomProfile'] ?: $variable['VariableProfile'];
        $profil = ($profil !== '') && IPS_VariableProfileExists($profil) ? IPS_GetVariableProfile($profil) : null;

        if (!empty($profil['Associations'])) {
            $optionen = [];
            foreach ($profil['Associations'] as $association) {
                $wert = $association['Value'];
                if ($variable['VariableType'] == 0) {
                    $wert = (bool) $wert;
                }
                $optionen[] = ['wert' => json_encode($wert), 'text' => $association['Name']];
            }

            return ['typ' => 'auswahl', 'optionen' => $optionen];
        }

        if (in_array($variable['VariableType'], [1, 2], true)) {
            return [
                'typ'    => 'zahl',
                'min'    => (float) ($profil['MinValue'] ?? 0),
                'max'    => (float) ($profil['MaxValue'] ?? 100),
                'schritt' => ($variable['VariableType'] == 1) ? 1 : 0.5,
                'einheit' => trim((string) ($profil['Suffix'] ?? '')),
            ];
        }

        return ['typ' => 'text'];
    }

    /**
     * Die Schaltpunkte, wie die Kachel sie zeigt und bedienen laesst:
     * Uhrzeit, Wochentage und ein Schalter je Punkt. Was ein Objekt auswaehlen
     * muss -- das Ziel -- bleibt der Konsole vorbehalten, dafuer braucht es
     * den Objektbaum.
     */
    private function Kachelpunkte(): array
    {
        $wochenuhr = ($this->ReadPropertyInteger('Mode') == 1);
        $zeilen = [];

        foreach ($this->Punkte() as $index => $punkt) {
            $astro = ((int) ($punkt['TimeType'] ?? 0)) != 0;

            $tage = null;
            if ($wochenuhr) {
                $tage = [];
                foreach (self::TAGE as $nummer => $kuerzel) {
                    $tage[] = ['kurz' => $kuerzel, 'an' => (bool) ($punkt['D' . $nummer] ?? false)];
                }
            }

            $zeit = json_decode((string) ($punkt['Time'] ?? ''), true);

            $ziel = (int) ($punkt['TargetID'] ?? 0);
            $variable = $this->IstSzene($ziel) ? 0 : $this->Zielvariable($ziel);

            $zeilen[] = [
                'index'   => $index,
                'aktiv'   => (bool) ($punkt['Active'] ?? true),
                'zeit'    => sprintf('%02d:%02d', (int) ($zeit['hour'] ?? 0), (int) ($zeit['minute'] ?? 0)),
                'astro'   => $astro ? $this->Zeittext($punkt) : null,
                'zeitart' => (int) ($punkt['TimeType'] ?? 0),
                'verschiebung' => (int) ($punkt['Offset'] ?? 0),
                'tage'    => $tage,
                'ziel'    => $ziel,
                'zieltext' => $this->Zieltext($punkt),
                'wert'    => (string) ($punkt['Value'] ?? 'null'),
                'wertfeld' => $this->Wertfeld($variable),
            ];
        }

        return $zeilen;
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
            $punkte[$index]['Ziel'] = $this->Zieltext($punkt, true);
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

    /**
     * $mitOrt stellt den Ordner voran, in dem das Ziel liegt. In der Liste ist
     * das der Raum -- "Deckenlicht" gibt es in jedem zweiten, erst
     * "Speicher · Deckenlicht" sagt, welches gemeint ist. Auf der Kachel ist
     * dafuer kein Platz, dort steht nur der Name.
     */
    private function Zieltext(array $punkt, bool $mitOrt = false): string
    {
        $ziel = (int) ($punkt['TargetID'] ?? 0);
        if (($ziel == 0) || !IPS_ObjectExists($ziel)) {
            return 'kein Ziel';
        }
        if ($this->IstSzene($ziel)) {
            return $this->Zielname($ziel, $ziel, $mitOrt) . ' aufrufen';
        }

        $variable = $this->Zielvariable($ziel);
        if ($variable == 0) {
            return $this->Zielname($ziel, $ziel, $mitOrt) . ' — keine bedienbare Variable';
        }

        return $this->Zielname($ziel, $variable, $mitOrt) . ' → '
            . $this->RegoWertText($variable, $this->RegoZielwert($variable, $punkt['Value'] ?? ''));
    }

    /**
     * "Value" sagt niemandem etwas -- dann nennt die Anzeige das Geraet, dem
     * die Variable gehoert.
     */
    private function Zielname(int $objektID, int $variable, bool $mitOrt = false): string
    {
        $benannt = $objektID;
        $name = IPS_GetName($objektID);

        // "Value" sagt niemandem etwas -- dann nennt die Anzeige das Geraet,
        // dem die Variable gehoert, und der Ordner rueckt eine Ebene hoeher.
        if (($objektID === $variable) && in_array($name, ['Value', 'Wert'], true)) {
            $eltern = IPS_GetObject($variable)['ParentID'];
            if ($eltern > 0) {
                $benannt = $eltern;
                $name = IPS_GetName($eltern);
            }
        }

        if (!$mitOrt) {
            return $name;
        }

        $ordner = IPS_GetObject($benannt)['ParentID'];

        return ($ordner > 0) ? IPS_GetName($ordner) . ' · ' . $name : $name;
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
