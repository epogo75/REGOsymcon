<?php
// REGOvisu-Deploy -- ein Skript, das auf einem leeren Symcon alles aufbaut.
//
//   1. installiert die REGOvisu-Modulbibliothek, falls sie fehlt
//   2. "Visu <Projekt>" mit Etagen und Räumen
//   3. den kompletten Adresskatalog des importierten ETS-Projekts unter
//      "REGOdeploy > KNX": Haupt- und Mittelgruppen mit ihren ETS-Namen, jede
//      Gruppenadresse als eigenes, exakt typisiertes Gerät am KNX Gateway --
//      auch die Adressen, die keiner REGOdeploy-Funktion gehören ("freie")
//   4. in jeden Raum die passenden REGOvisu-Kacheln, verdrahtet mit den
//      Variablen genau dieser Geräte -- jede Gruppenadresse existiert damit
//      nur einmal in Symcon
//
// In den Räumen stehen damit nur die Kacheln; alles Technische liegt
// darunter in "REGOdeploy".
//
// Erneutes Ausführen ist sicher: Umbenennungen, Umzüge und geänderte Adressen
// werden nachgezogen; Objekte zu gelöschten Funktionen landen unter
// "REGOdeploy – Verwaist" statt gelöscht zu werden.
//
// Nichts an der Zuordnung ist geraten: welche Aktion zu welchem Bedienelement
// gehört, steht im Funktionenkatalog von REGOdeploy (/api/funktionenkatalog);
// welche Gruppenadresse dahinter liegt, kommt aus dem Projekt-Export; und der
// Variablen-Ident eines KNX-Geräts ist die Gruppenadresse selbst
// ("GA_1_0_0_Value"). Rückmeldeadressen werden in ihre Primäradresse
// eingefaltet, deshalb zeigen Schreib- und Rückmelde-Eigenschaft bewusst auf
// dieselbe Variable.

// Diese fünf Zeilen füllt REGOdeploy beim Übertragen aus (push.py ersetzt sie
// wörtlich) -- Schreibweise deshalb bitte nicht ändern.
$REGODEPLOY_BASE_URL = "http://CHANGE_ME:8001";
$REGODEPLOY_USERNAME = "CHANGE_ME";
$REGODEPLOY_PASSWORD = "CHANGE_ME";
$REGODEPLOY_PROJECT_ID = 0; // CHANGE_ME
$KNX_GATEWAY_INSTANCE_ID = 0; // CHANGE_ME -- die Instanz-ID deines KNX Gateway

// Kachelmaße je Zielgerät und Ausrichtung: Breite in Rasterspalten, Höhe in
// Rasterzeilen. Quer hat das Raster 12 Spalten, hochkant 6 -- Breite 6 heißt
// quer also halbe und hochkant volle Breite. Unter zwei Zeilen Höhe schneidet
// die Kachel Symbol und Knöpfe ab.
$KACHEL_MASSE = [
    'Desktop' => ['quer' => ['breite' => 3,  'hoehe' => 2], 'hoch' => ['breite' => 3, 'hoehe' => 2]],
    'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
    'Tablet'  => ['quer' => ['breite' => 6,  'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
];

// Die Wetterstation zeigt ein ganzes Werteraster und braucht mehr Platz als
// eine gewöhnliche Kachel -- deshalb eigene Maße, gleicher Aufbau.
// Zeitspanne der Diagramme in der Visualisierung:
// 0 Stunde, 1 Tag, 2 Woche, 3 Monat, 4 Jahr, 5 Jahrzehnt.
$GRAPH_ZEITSPANNE = 0;

$WETTER_MASSE = [
    'Desktop' => ['quer' => ['breite' => 6,  'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
    'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 5], 'hoch' => ['breite' => 6, 'hoehe' => 5]],
    'Tablet'  => ['quer' => ['breite' => 12, 'hoehe' => 4], 'hoch' => ['breite' => 6, 'hoehe' => 4]],
];

const REGOVISU_REPOSITORY = 'https://github.com/epogo75/SymconREGOvisu';

// Modul-GUIDs der REGOvisu-Bibliothek
const RGV_SCHALTEN = '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}';
const RGV_DIMMEN   = '{A4507CF7-C921-467C-BD01-699C862B9F5C}';
const RGV_JALOUSIE = '{23F455EC-9236-480B-B02F-E10CE43DBDE2}';
const RGV_KLIMA    = '{FEB37553-F02A-4F1B-A669-15BCD71E0712}';
const RGV_SZENE    = '{C2314D3B-F6AD-40E2-B5B7-6DB850E0AD5E}';
const RGV_SENSOR   = '{0871A6F2-8912-4EC0-9C4F-616982DAFF34}';
const RGV_WETTER   = '{6EFCE386-425F-4AF5-8440-B93CAA0B3C2E}';
const RGV_INFO     = '{63561319-730C-4139-8F95-1DA3BD142C83}';

// Wetterstation: Reihenfolge, Nachkommastellen und ob "wahr" ein Alarm ist.
// Die Aktionsnamen sind die des REGOdeploy-Funktionenkatalogs; was hier nicht
// steht, landet unten in der Liste mit den Vorgaben des Variablenprofils.
const WETTER_AKTIONEN = [
    'Außentemperatur'        => ['rang' => 10, 'digits' => 1,  'alarm' => false],
    'Windgeschwindigkeit'    => ['rang' => 20, 'digits' => 1,  'alarm' => false],
    'Helligkeitswert Ost'    => ['rang' => 30, 'digits' => 0,  'alarm' => false],
    'Helligkeitswert Süd'    => ['rang' => 31, 'digits' => 0,  'alarm' => false],
    'Helligkeitswert West'   => ['rang' => 32, 'digits' => 0,  'alarm' => false],
    'Helligkeitswert gesamt' => ['rang' => 33, 'digits' => 0,  'alarm' => false],
    'Azimut'                 => ['rang' => 40, 'digits' => 0,  'alarm' => false],
    'Elevation'              => ['rang' => 41, 'digits' => 0,  'alarm' => false],
    'Datum'                  => ['rang' => 50, 'digits' => -1, 'alarm' => false],
    'Uhrzeit'                => ['rang' => 51, 'digits' => -1, 'alarm' => false],
    'Regen'                  => ['rang' => 60, 'digits' => -1, 'alarm' => true],
    'Windalarm 1'            => ['rang' => 61, 'digits' => -1, 'alarm' => true],
    'Windalarm 2'            => ['rang' => 62, 'digits' => -1, 'alarm' => true],
    'Frostschutz'            => ['rang' => 63, 'digits' => -1, 'alarm' => true],
    'Hitzeschutz'            => ['rang' => 64, 'digits' => -1, 'alarm' => true],
    'Dämmerung'              => ['rang' => 70, 'digits' => -1, 'alarm' => false],
    'Tag/Nacht (Tag=0)'      => ['rang' => 71, 'digits' => -1, 'alarm' => false],
    'Sonne Fassade Nord'     => ['rang' => 80, 'digits' => -1, 'alarm' => false],
    'Sonne Fassade Ost'      => ['rang' => 81, 'digits' => -1, 'alarm' => false],
    'Sonne Fassade Süd'      => ['rang' => 82, 'digits' => -1, 'alarm' => false],
    'Sonne Fassade West'     => ['rang' => 83, 'digits' => -1, 'alarm' => false],
];

const MODULE_CONTROL_GUID = '{B8A5067A-AFC2-3798-FEDC-BCD02A45615E}';
const TILE_VISU_GUID      = '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}';
const KNX_GATEWAY_GUID    = '{1C902193-B044-43B8-9433-419F09C641B8}';
const KNX_DEVICE_GUID     = '{FB223058-3084-C5D0-C7A2-3B8D2E73FE8A}';
const ARCHIVE_GUID        = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
const LOCATION_GUID       = '{45E97A63-F870-408A-B259-2933F7EABF74}';

// Mehrteilige DPTs: das KNX-Gerät legt je Adresse mehrere Variablen an, die
// Idents enden dann auf "Value0", "Value1" statt auf "Value". DPT 18.001 etwa
// trennt die Szenennummer vom Bit "Aufrufen/Speichern".
const DPT_TEILE = [
    '18.001' => ['wert' => 'Value1', 'zusatz' => 'Value0'],
];

const VISU_ROOT_IDENT   = 'regodeploy_visu_root';
const DEPLOY_ROOT_IDENT = 'regodeploy_root';
const ORPHAN_ROOT_IDENT = 'regodeploy_orphan_root';
const KNX_ROOT_IDENT    = 'regodeploy_knx_root';
const KNX_GERAETE_IDENT = 'regovisu_knx_geraete';
const INFO_IDENT        = 'regovisu_info';

const ETAGE_PREFIX   = 'regodeploy_etage_';
const RAUM_PREFIX    = 'regodeploy_raum_';
const GERAET_PREFIX  = 'regodeploy_sammelinstanz_';
const HG_PREFIX      = 'regodeploy_hg_';
const MG_PREFIX      = 'regodeploy_mg_';
const GA_PREFIX      = 'regodeploy_ga_';
const KACHEL_PREFIX  = 'regovisu_kachel_';
const LINK_PREFIX    = 'regovisu_link_';

// Beschriftung der Verknüpfungen, die unter jeder Kachel liegen -- sie
// erscheinen so auf der Detailseite der Kachel in der Visualisierung.
const LINK_BESCHRIFTUNG = [
    'SwitchVariable'           => 'Schalten',
    'StatusVariable'           => 'Status',
    'DimVariable'              => 'Dimmen absolut',
    'BrightnessVariable'       => 'Helligkeit',
    'MoveVariable'             => 'Fahren',
    'StepVariable'             => 'Stopp',
    'PositionVariable'         => 'Position',
    'PositionFeedbackVariable' => 'Position Rückmeldung',
    'ActualVariable'           => 'Ist-Temperatur',
    'SetpointVariable'         => 'Soll-Temperatur',
    'SetpointFeedbackVariable' => 'Soll-Temperatur Rückmeldung',
    'SceneVariable'            => 'Szenennummer',
    'ModeVariable'             => 'Aufrufen/Speichern',
    'ValueVariable'            => 'Messwert',
    'SecondaryVariable'        => 'Zweiter Zustand',
    'BatteryVariable'          => 'Batteriestand',
    'SunriseVariable'          => 'Sonnenaufgang',
    'SunsetVariable'           => 'Sonnenuntergang',
    'TemperatureVariable'      => 'Außentemperatur',
];

const VERWALTETE_PREFIXE = [
    ETAGE_PREFIX, RAUM_PREFIX, GERAET_PREFIX, HG_PREFIX, MG_PREFIX, GA_PREFIX, KACHEL_PREFIX,
];
const VERWALTETE_IDENTS = [
    VISU_ROOT_IDENT, DEPLOY_ROOT_IDENT, ORPHAN_ROOT_IDENT, KNX_ROOT_IDENT, KNX_GERAETE_IDENT,
    INFO_IDENT,
];

// Funktionstyp (optional "|unterart") -> Kachel-Modul und Eigenschaften.
//
// Je Eigenschaft stehen die Aktionen in der Reihenfolge, in der sie probiert
// werden; die Namen sind wörtlich die des REGOdeploy-Funktionenkatalogs.
// Mehrere Kandidaten heißt nicht "irgendwas Passendes suchen", sondern: die
// Rückmeldung ist die genauere Quelle, die Schaltadresse der Rückfallwert,
// wenn das Projekt die Rückmeldung nicht pflegt.
const KACHEL_MAPPING = [
    'schalten' => [
        'module' => RGV_SCHALTEN,
        'props' => [
            'StatusVariable' => ['Status', 'Schalten'],
            'SwitchVariable' => ['Schalten'],
        ],
    ],
    'dimmen' => [
        'module' => RGV_DIMMEN,
        'props' => [
            'StatusVariable'     => ['Status', 'Schalten'],
            'SwitchVariable'     => ['Schalten'],
            'BrightnessVariable' => ['Zustandswert', 'Dimmen absolut'],
            'DimVariable'        => ['Dimmen absolut'],
        ],
    ],
    'dimmen|rgb' => [
        'module' => RGV_DIMMEN,
        'props' => [
            'StatusVariable'     => ['Allgemein Status', 'Allgemein Schalten'],
            'SwitchVariable'     => ['Allgemein Schalten'],
            'BrightnessVariable' => ['Allgemein Helligkeit Rückmeldung', 'Allgemein Helligkeit'],
            'DimVariable'        => ['Allgemein Helligkeit'],
        ],
    ],
    'jalousie' => [
        'module' => RGV_JALOUSIE,
        'props' => [
            'MoveVariable'             => ['Move'],
            'StepVariable'             => ['Step'],
            'PositionVariable'         => ['Move to Position'],
            'PositionFeedbackVariable' => ['Position Feedback', 'Move to Position'],
        ],
    ],
    'temperatur' => [
        'module' => RGV_KLIMA,
        'props' => [
            'ActualVariable'   => ['Ist-Temperatur'],
            'SetpointVariable' => ['Soll-Temperatur'],
        ],
    ],
    'klima' => [
        'module' => RGV_KLIMA,
        'props' => [
            'ActualVariable'           => ['Ist-Temperatur'],
            'SetpointVariable'         => ['Soll-Temperatur'],
            'SetpointFeedbackVariable' => ['Soll-Temperatur Rückmeldung', 'Soll-Temperatur'],
        ],
    ],
    'szene' => [
        'module' => RGV_SZENE,
        'props' => [
            'SceneVariable' => ['Szene'],
            'ModeVariable'  => ['Szene'],
        ],
        'teile' => [
            'SceneVariable' => 'wert',
            'ModeVariable'  => 'zusatz',
        ],
        // Drei Plätze zum Beschriften, wie in der Visu von REGObaseX1. Wird
        // nur beim Anlegen gesetzt -- eigene Namen bleiben erhalten.
        'vorbelegung' => [
            'Scenes' => '[{"Label":"Szene 1","Number":1},{"Label":"Szene 2","Number":2},{"Label":"Szene 3","Number":3}]',
        ],
    ],

    // Messwerte. Die Aktion je Unterart steht im Funktionenkatalog; für eine
    // Unterart, die hier nicht steht, greift die Regel weiter unten: hat die
    // Funktion genau eine Adresse, ist das eindeutig der Messwert.
    'wetterstation' => [
        'module' => RGV_WETTER,
        // Diese Kachel bekommt eine Liste statt einzelner Felder -- siehe
        // wetterstation_readings() weiter unten.
        'props' => [],
        'liste' => 'Readings',
    ],
    'sensor' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable' => [],
        ],
    ],
    'sensor|temperatur' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable' => ['Temperatur'],
        ],
        // Das KNX-Temperaturprofil bringt zwei Nachkommastellen mit; eine
        // reicht und ist das, was REGObaseX1 zeigt.
        'einstellungen' => ['Digits' => 1],
    ],
    'sensor|feuchte' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable' => ['Feuchte'],
        ],
        'einstellungen' => ['Digits' => 0],
    ],
    'sensor|co2' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable' => ['CO2 Wert'],
        ],
        'einstellungen' => ['Digits' => 0],
    ],
    'sensor|fensterkontakt' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable'     => ['Fenster offen/geschlossen'],
            'SecondaryVariable' => ['Fenster gekippt/geschlossen'],
            'BatteryVariable'   => ['Batteriestand'],
        ],
        // Die mitgelieferten KNX-Profile sind englisch beschriftet
        // ("Closed", "On") -- hier stehen die deutschen Texte.
        'einstellungen' => [
            'TextTrue'            => 'offen',
            'TextFalse'           => 'geschlossen',
            'AlarmOnTrue'         => true,
            'SecondaryLabel'      => 'Fenster gekippt',
            'SecondaryTextTrue'   => 'Ja',
            'SecondaryTextFalse'  => 'Nein',
        ],
    ],
    'sensor|rauchmelder' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable'   => ['Rauchmelder ausgelöst'],
            'BatteryVariable' => ['Batteriestand'],
        ],
        'einstellungen' => [
            'TextTrue'    => 'ausgelöst',
            'TextFalse'   => 'ruhig',
            'AlarmOnTrue' => true,
        ],
    ],
    'sensor|wassermelder' => [
        'module' => RGV_SENSOR,
        'props' => [
            'ValueVariable'   => ['Wassermelder ausgelöst'],
            'BatteryVariable' => ['Batteriestand'],
        ],
        'einstellungen' => [
            'TextTrue'    => 'ausgelöst',
            'TextFalse'   => 'trocken',
            'AlarmOnTrue' => true,
        ],
    ],
];

// ---- Hilfsmittel ----

function http_post_form($url, $data)
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'ignore_errors' => true,
        'timeout' => 15,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        throw new Exception("POST $url fehlgeschlagen");
    }
    return json_decode($result, true);
}

function http_get_json($url, $token)
{
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $token\r\n",
        'ignore_errors' => true,
        'timeout' => 60,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        throw new Exception("GET $url fehlgeschlagen");
    }
    return json_decode($result, true);
}

/**
 * Installiert die Modulbibliothek über die Modulverwaltung, wenn sie fehlt.
 */
function ensure_module_installed()
{
    if (@IPS_GetModule(RGV_SCHALTEN) !== false) {
        return 'war schon installiert';
    }

    $control = IPS_GetInstanceListByModuleID(MODULE_CONTROL_GUID);
    if (empty($control)) {
        throw new Exception('Keine Modulverwaltung gefunden -- Modul bitte von Hand installieren');
    }

    MC_CreateModule($control[0], REGOVISU_REPOSITORY);

    for ($i = 0; $i < 30; $i++) {
        if (@IPS_GetModule(RGV_SCHALTEN) !== false) {
            return 'neu installiert von ' . REGOVISU_REPOSITORY;
        }
        IPS_Sleep(1000);
    }

    throw new Exception('Modul geladen, aber nicht verfügbar -- Symcon neu starten und erneut ausführen');
}

/**
 * DPT-Haupttyp -> Symcon-Modul, gelesen aus der Modulliste dieser Installation.
 *
 * Symcon liefert je Haupttyp ein Modul "KNX DPT <n>"; die Dimension ist immer
 * der Nebentyp. Die Zuordnung wird nicht abgeschrieben, sondern hier gebildet
 * -- so stimmt sie auch, wenn Symcon neue Typen nachliefert.
 */
function dpt_module()
{
    static $module = null;
    if ($module !== null) {
        return $module;
    }

    $module = [];
    foreach (IPS_GetModuleList() as $guid) {
        if (preg_match('/^KNX DPT (\d+)$/', IPS_GetModule($guid)['ModuleName'], $treffer)) {
            $module[(int) $treffer[1]] = $guid;
        }
    }
    return $module;
}

/**
 * "9.001" -> Modul-GUID und Dimension.
 */
function dpt_zerlegen($dptId)
{
    $teile = explode('.', (string) $dptId, 2);
    if ((count($teile) !== 2) || !is_numeric($teile[0]) || !is_numeric($teile[1])) {
        return null;
    }

    $module = dpt_module();
    $haupt = (int) $teile[0];
    if (!isset($module[$haupt])) {
        return null;
    }

    return ['modul' => $module[$haupt], 'dimension' => (int) $teile[1]];
}

function ist_verwaltet($ident)
{
    if (in_array($ident, VERWALTETE_IDENTS, true)) {
        return true;
    }
    foreach (VERWALTETE_PREFIXE as $prefix) {
        if (strpos($ident, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Sammelt alle vom Skript verwalteten Objekte (Kategorien und Instanzen).
 */
function collect_idents($parentId, &$index)
{
    foreach (IPS_GetChildrenIDs($parentId) as $childId) {
        $obj = IPS_GetObject($childId);
        if (($obj['ObjectIdent'] !== '') && ist_verwaltet($obj['ObjectIdent'])) {
            $index[$obj['ObjectIdent']] = ['id' => $childId, 'parent' => $obj['ParentID']];
        }
        if ($obj['ObjectType'] === 0) {
            collect_idents($childId, $index);
        }
    }
}

function sync_category($ident, $name, $position, $parentId, &$index, &$visited)
{
    $visited[$ident] = true;

    if (isset($index[$ident])) {
        $id = $index[$ident]['id'];
        if ($index[$ident]['parent'] !== $parentId) {
            IPS_SetParent($id, $parentId);
            $index[$ident]['parent'] = $parentId;
        }
        if (IPS_GetName($id) !== $name) {
            IPS_SetName($id, $name);
        }
        IPS_SetPosition($id, $position);
        return $id;
    }

    $id = IPS_CreateCategory();
    IPS_SetIdent($id, $ident);
    IPS_SetName($id, $name);
    IPS_SetParent($id, $parentId);
    IPS_SetPosition($id, $position);
    $index[$ident] = ['id' => $id, 'parent' => $parentId];
    return $id;
}

/**
 * Eine einzelne Gruppenadresse als exakt typisiertes Gerät.
 */
function sync_gruppenadresse($adresse, $name, $dptId, $parentId, $position, $gatewayId, &$index, &$visited)
{
    $typ = dpt_zerlegen($dptId);
    if ($typ === null) {
        return 'übersprungen';
    }

    list($h, $m, $s) = array_map('intval', explode('/', $adresse));
    $ident = sprintf('%s%d_%d_%d', GA_PREFIX, $h, $m, $s);
    $visited[$ident] = true;

    if (isset($index[$ident])) {
        $id = $index[$ident]['id'];
        if ($index[$ident]['parent'] !== $parentId) {
            IPS_SetParent($id, $parentId);
            $index[$ident]['parent'] = $parentId;
        }
        $neu = false;
    } else {
        $id = IPS_CreateInstance($typ['modul']);
        IPS_SetProperty($id, 'Address1', $h);
        IPS_SetProperty($id, 'Address2', $m);
        IPS_SetProperty($id, 'Address3', $s);
        IPS_SetProperty($id, 'Dimension', $typ['dimension']);
        IPS_SetProperty($id, 'CapabilityRead', false);
        IPS_SetProperty($id, 'CapabilityWrite', true);
        IPS_SetProperty($id, 'CapabilityReceive', true);
        IPS_SetProperty($id, 'CapabilityTransmit', false);
        IPS_SetProperty($id, 'EmulateStatus', true);
        IPS_ApplyChanges($id);
        IPS_SetIdent($id, $ident);
        IPS_SetParent($id, $parentId);
        $index[$ident] = ['id' => $id, 'parent' => $parentId];
        $neu = true;
    }

    if (IPS_GetName($id) !== $name) {
        IPS_SetName($id, $name);
    }
    IPS_SetPosition($id, $position);
    if (IPS_GetInstance($id)['ConnectionID'] !== $gatewayId) {
        IPS_ConnectInstance($id, $gatewayId);
    }

    return $neu ? 'neu' : 'vorhanden';
}

/**
 * Gruppenadresse -> Variable des zugehörigen Katalog-Geräts.
 *
 * Jede Adresse steht genau einmal in Symcon, als eigenes Gerät unter
 * "REGOdeploy > KNX". Dessen Variable heißt "Value"; nur mehrteilige DPTs
 * legen mehrere an (siehe DPT_TEILE).
 */
function variable_of_ga($groupAddress, $dptId, $teil, &$index)
{
    list($h, $m, $s) = array_map('intval', explode('/', $groupAddress));
    $ident = sprintf('%s%d_%d_%d', GA_PREFIX, $h, $m, $s);
    if (!isset($index[$ident])) {
        return 0;
    }

    $kandidaten = ['Value'];
    if (isset(DPT_TEILE[(string) $dptId][$teil])) {
        array_unshift($kandidaten, DPT_TEILE[(string) $dptId][$teil]);
    }

    foreach ($kandidaten as $kandidat) {
        $varId = @IPS_GetObjectIDByIdent($kandidat, $index[$ident]['id']);
        if ($varId !== false) {
            return $varId;
        }
    }

    return 0;
}

/**
 * Aktion -> Variable.
 *
 * Rückmeldungen zeigen auf ihre eigene Adresse, nicht auf die des
 * Schreibbefehls -- anders als beim früheren Sammelgerät, das beide in eine
 * Zeile gefaltet hat.
 */
function variable_for_aktion($funktion, $aktion, &$index, $teil = 'wert')
{
    foreach ($funktion['adressen'] as $adresse) {
        if ($adresse['aktion'] === $aktion) {
            return variable_of_ga($adresse['group_address'], $adresse['dpt_id'], $teil, $index);
        }
        foreach ($adresse['feedback_addresses'] as $fb) {
            if ($fb['aktion'] === $aktion) {
                return variable_of_ga($fb['group_address'], $adresse['dpt_id'], $teil, $index);
            }
        }
    }
    return 0;
}

/**
 * Nimmt den Messwert-Geräten das Schreibrecht.
 *
 * Ein KNX-Gerät mit "CapabilityWrite" hängt seiner Variable eine Aktion an --
 * die Visualisierung bietet dann eine Bedienung an, obwohl ein Sensor nichts
 * entgegennimmt. Ohne Schreibrecht bleibt die Variable eine reine Anzeige;
 * empfangen wird weiterhin.
 */
function setze_nur_lesen($variableIds)
{
    $geaendert = 0;

    foreach (array_unique(array_filter($variableIds)) as $variableID) {
        if (!IPS_VariableExists($variableID)) {
            continue;
        }
        $geraet = IPS_GetObject($variableID)['ParentID'];
        if (!IPS_InstanceExists($geraet)) {
            continue;
        }
        $config = json_decode(IPS_GetConfiguration($geraet), true);
        if (!array_key_exists('CapabilityWrite', $config) || ($config['CapabilityWrite'] === false)) {
            continue;
        }
        IPS_SetProperty($geraet, 'CapabilityWrite', false);
        IPS_ApplyChanges($geraet);
        $geaendert++;
    }

    return $geaendert;
}

/**
 * Schaltet Aufzeichnung und Diagramm für Messwerte ein.
 */
function aktiviere_aufzeichnung($variableIds)
{
    $archive = IPS_GetInstanceListByModuleID(ARCHIVE_GUID);
    if (empty($archive)) {
        return 0;
    }
    $archiveId = $archive[0];

    $neu = 0;
    foreach (array_unique(array_filter($variableIds)) as $variableID) {
        if (!IPS_VariableExists($variableID)) {
            continue;
        }
        if (!AC_GetLoggingStatus($archiveId, $variableID)) {
            AC_SetLoggingStatus($archiveId, $variableID, true);
            $neu++;
        }
        if (!AC_GetGraphStatus($archiveId, $variableID)) {
            AC_SetGraphStatus($archiveId, $variableID, true);
        }
    }
    if ($neu > 0) {
        IPS_ApplyChanges($archiveId);
    }

    return $neu;
}

/**
 * Die Infokachel auf der Startseite: Sonnenaufgang, Sonnenuntergang und
 * Außentemperatur. Die Sonnenzeiten kommen aus Symcons Location-Instanz.
 */
function sync_infokachel($visuId, $aussentemperatur, &$index, &$visited)
{
    $location = IPS_GetInstanceListByModuleID(LOCATION_GUID);
    $sunrise = 0;
    $sunset = 0;
    if (!empty($location)) {
        $sunrise = @IPS_GetObjectIDByIdent('Sunrise', $location[0]);
        $sunset = @IPS_GetObjectIDByIdent('Sunset', $location[0]);
        $sunrise = ($sunrise === false) ? 0 : $sunrise;
        $sunset = ($sunset === false) ? 0 : $sunset;
    }

    if (($sunrise == 0) && ($sunset == 0) && ($aussentemperatur == 0)) {
        return 0;
    }

    $visited[INFO_IDENT] = true;

    if (isset($index[INFO_IDENT])) {
        $id = $index[INFO_IDENT]['id'];
        if ($index[INFO_IDENT]['parent'] !== $visuId) {
            IPS_SetParent($id, $visuId);
            $index[INFO_IDENT]['parent'] = $visuId;
        }
    } else {
        $id = IPS_CreateInstance(RGV_INFO);
        IPS_SetIdent($id, INFO_IDENT);
        IPS_SetParent($id, $visuId);
        IPS_SetName($id, 'Info');
        IPS_SetPosition($id, -1);
        $index[INFO_IDENT] = ['id' => $id, 'parent' => $visuId];
    }

    $current = json_decode(IPS_GetConfiguration($id), true);
    $soll = [
        'SunriseVariable' => $sunrise,
        'SunsetVariable' => $sunset,
        'TemperatureVariable' => $aussentemperatur,
    ];
    $apply = false;
    foreach ($soll as $property => $wert) {
        if (($current[$property] ?? 0) !== $wert) {
            IPS_SetProperty($id, $property, $wert);
            $apply = true;
        }
    }
    if ($apply) {
        IPS_ApplyChanges($id);
    }

    sync_links($id, [
        'Sonnenaufgang' => $sunrise,
        'Sonnenuntergang' => $sunset,
        'Außentemperatur' => $aussentemperatur,
    ]);

    return $id;
}

/**
 * Baut die Messwert-Liste der Wetterstation: jede Adresse der Funktion wird
 * eine Zeile, sortiert nach der Reihenfolge oben.
 */
function wetterstation_readings($funktion, &$index)
{
    $zeilen = [];

    foreach ($funktion['adressen'] as $adresse) {
        $varId = variable_for_aktion($funktion, $adresse['aktion'], $index);
        if ($varId == 0) {
            continue;
        }
        $vorgabe = WETTER_AKTIONEN[$adresse['aktion']] ?? ['rang' => 999, 'digits' => -1, 'alarm' => false];
        $zeilen[] = [
            'rang' => $vorgabe['rang'],
            'zeile' => [
                'VariableID' => $varId,
                'Label' => $adresse['aktion'],
                'Digits' => $vorgabe['digits'],
                'Alarm' => $vorgabe['alarm'],
            ],
        ];
    }

    usort($zeilen, function ($a, $b) {
        return ($a['rang'] <=> $b['rang']) ?: strcmp($a['zeile']['Label'], $b['zeile']['Label']);
    });

    return array_column($zeilen, 'zeile');
}

/**
 * Richtet die Kachel-Visualisierung ein: sie startet im Visu-Ordner des
 * Projekts (dessen Etagen sind damit die erste Ebene), und jede REGOvisu-
 * Kachel bekommt die eingestellten Maße im Raster.
 *
 * Die Kachelkonfiguration ist eine Map "Name -> Konfiguration"; bestehende
 * Konfigurationen bleiben erhalten, nur die Maße der eigenen Kacheln werden
 * gesetzt.
 */
function richte_visualisierung_ein($visuRootId, $kachelIds, $alleMasse, $wetterMasse, $zeitspanne)
{
    $angepasst = 0;

    foreach (IPS_GetInstanceListByModuleID(TILE_VISU_GUID) as $visId) {
        $config = json_decode(IPS_GetConfiguration($visId), true);
        $aendern = false;
        if ($config['BaseID'] !== $visuRootId) {
            IPS_SetProperty($visId, 'BaseID', $visuRootId);
            $aendern = true;
        }
        if (($config['GraphTimeSpan'] ?? null) !== $zeitspanne) {
            IPS_SetProperty($visId, 'GraphTimeSpan', $zeitspanne);
            $aendern = true;
        }
        if ($aendern) {
            IPS_ApplyChanges($visId);
        }

        if (empty($kachelIds)) {
            continue;
        }

        $liste = finde_gridkonfiguration(json_decode(IPS_GetConfigurationForm($visId), true)['elements']);
        if (!is_array($liste)) {
            continue;
        }

        $map = [];
        foreach ($liste as $eintrag) {
            // Der Name ist "~Desktop", "~Phone", "~Tablet" -- die Tilde faellt weg.
            $geraet = ltrim($eintrag['Name'], '~');
            $masse = $alleMasse[$geraet] ?? reset($alleMasse);
            $wetter = $wetterMasse[$geraet] ?? reset($wetterMasse);

            $config = is_string($eintrag['Config']) ? json_decode($eintrag['Config'], true) : $eintrag['Config'];
            if (!is_array($config)) {
                continue;
            }

            foreach (['landscape', 'portrait'] as $lage) {
                if (!isset($config[$lage]) || (($config[$lage]['crossAxis'] ?? null) === null)) {
                    continue;
                }
                $spalten = $config[$lage]['crossAxis'];
                $schluessel = ($lage === 'landscape') ? 'quer' : 'hoch';

                $fuerLage = $masse[$schluessel];
                $masseFuerKachel = [
                    'height' => max(1, (int) $fuerLage['hoehe']),
                    'width' => max(1, min((int) $fuerLage['breite'], $spalten)),
                ];
                $fuerWetter = $wetter[$schluessel];
                $masseFuerWetter = [
                    'height' => max(1, (int) $fuerWetter['hoehe']),
                    'width' => max(1, min((int) $fuerWetter['breite'], $spalten)),
                ];

                $dim = (array) ($config[$lage]['individualDimensions'] ?? []);
                foreach ($kachelIds as $id) {
                    $eigen = $masseFuerKachel;
                    if (IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === RGV_WETTER) {
                        $eigen = $masseFuerWetter;
                    }
                    $dim[(string) $id] = $eigen;
                }
                $config[$lage]['individualDimensions'] = empty($dim) ? new stdClass() : $dim;

                foreach (['individualConfig', 'individualPositions'] as $feld) {
                    if (empty($config[$lage][$feld])) {
                        $config[$lage][$feld] = new stdClass();
                    }
                }
            }
            $map[$eintrag['Name']] = $config;
        }

        if (!empty($map) && VISU_SaveGridConfiguration($visId, json_encode($map))) {
            $angepasst++;
        }
    }

    return $angepasst;
}

/**
 * Fischt die Kachelkonfigurations-Liste aus dem Konfigurationsformular.
 */
function finde_gridkonfiguration($items)
{
    foreach ($items as $item) {
        if (($item['name'] ?? '') === 'GridConfiguration') {
            return $item['values'] ?? [];
        }
        foreach (['items', 'elements', 'actions'] as $schluessel) {
            if (isset($item[$schluessel]) && is_array($item[$schluessel])) {
                $treffer = finde_gridkonfiguration($item[$schluessel]);
                if ($treffer !== null) {
                    return $treffer;
                }
            }
        }
    }
    return null;
}

/**
 * Legt unter eine Kachel je verdrahteter Variable eine Verknüpfung.
 *
 * Die Detailseite einer Kachel zeigt deren Kindobjekte -- damit stehen dort
 * die beteiligten Gruppenadressen mit ihren eigenen Verläufen, ohne dass die
 * Kachel selbst etwas zeichnen muss. Verknüpfungen zu Variablen, die nicht
 * mehr verdrahtet sind, verschwinden wieder.
 *
 * $eintraege ist "Beschriftung => Variablen-ID".
 */
function sync_links($kachelId, array $eintraege)
{
    $gewuenscht = [];
    $position = 0;

    foreach ($eintraege as $beschriftung => $variableID) {
        $variableID = (int) $variableID;
        if (($variableID == 0) || !IPS_VariableExists($variableID)) {
            continue;
        }

        $ident = LINK_PREFIX . preg_replace('/[^a-z0-9]+/', '_', strtolower(
            iconv('UTF-8', 'ASCII//TRANSLIT', (string) $beschriftung)
        ));
        if (isset($gewuenscht[$ident])) {
            continue;
        }
        $gewuenscht[$ident] = true;

        $linkId = @IPS_GetObjectIDByIdent($ident, $kachelId);
        if ($linkId === false) {
            $linkId = IPS_CreateLink();
            IPS_SetIdent($linkId, $ident);
            IPS_SetParent($linkId, $kachelId);
        }
        if (IPS_GetLink($linkId)['TargetID'] !== $variableID) {
            IPS_SetLinkTargetID($linkId, $variableID);
        }
        if (IPS_GetName($linkId) !== $beschriftung) {
            IPS_SetName($linkId, (string) $beschriftung);
        }
        IPS_SetPosition($linkId, $position++);
    }

    // Was nicht mehr gebraucht wird, verschwindet.
    foreach (IPS_GetChildrenIDs($kachelId) as $kind) {
        $obj = IPS_GetObject($kind);
        if (($obj['ObjectType'] == 6) && (strpos($obj['ObjectIdent'], LINK_PREFIX) === 0)
            && !isset($gewuenscht[$obj['ObjectIdent']])) {
            IPS_DeleteLink($kind);
        }
    }

    return count($gewuenscht);
}

// ---- Ablauf ----

$modulStatus = ensure_module_installed();

// Gateway: die von REGOdeploy konfigurierte Instanz, sonst die einzige im
// System -- so läuft das Skript auch auf einem Symcon, das REGOdeploy noch
// nicht kennt.
$gatewayId = $KNX_GATEWAY_INSTANCE_ID;
if (($gatewayId == 0) || !IPS_InstanceExists($gatewayId)) {
    $gateways = IPS_GetInstanceListByModuleID(KNX_GATEWAY_GUID);
    if (empty($gateways)) {
        throw new Exception('Kein KNX Gateway in Symcon -- bitte zuerst eine KNX-Gateway-Instanz anlegen');
    }
    $gatewayId = $gateways[0];
}

$login = http_post_form("$REGODEPLOY_BASE_URL/api/auth/login", [
    'username' => $REGODEPLOY_USERNAME,
    'password' => $REGODEPLOY_PASSWORD,
]);
if (!isset($login['access_token'])) {
    throw new Exception('REGOdeploy-Login fehlgeschlagen: ' . json_encode($login));
}
$token = $login['access_token'];

$tree = http_get_json("$REGODEPLOY_BASE_URL/api/projects/$REGODEPLOY_PROJECT_ID/export/symcon-tree", $token);
if (!isset($tree['etagen'])) {
    throw new Exception('Unerwartete Antwort von /export/symcon-tree: ' . json_encode($tree));
}

// Unterart und Sichtbarkeits-Häkchen stehen nicht im Symcon-Export, aber in
// der Funktionsliste -- beides wird über die Funktions-ID zusammengeführt.
$meta = [];
foreach (http_get_json("$REGODEPLOY_BASE_URL/api/funktionen?project_id=$REGODEPLOY_PROJECT_ID", $token) as $f) {
    $meta[$f['id']] = $f;
}

$index = [];
collect_idents(0, $index);
$visited = [];

$visuId = sync_category(VISU_ROOT_IDENT, 'Visu ' . $tree['project_name'], 0, 0, $index, $visited);
$rootId = sync_category(DEPLOY_ROOT_IDENT, 'REGOdeploy', 1, 0, $index, $visited);

// Das laufende Skript legt sich selbst in den REGOdeploy-Ordner.
if (isset($_IPS['SELF']) && ($_IPS['SELF'] > 0) && IPS_ObjectExists($_IPS['SELF'])
    && (IPS_GetObject($_IPS['SELF'])['ParentID'] !== $rootId)) {
    IPS_SetParent($_IPS['SELF'], $rootId);
}

$kachelIds = [];
$links = 0;
$messwerte = [];
$aussentemperatur = 0;
$kachelnNeu = 0;
$kachelnAktualisiert = 0;
$kachelnUnveraendert = 0;
$raeume = 0;
$hinweise = [];

// Der Adresskatalog. Im Modus "freie Gruppenadressen" ist die Wahrheit das
// importierte ETS-Projekt: dessen Haupt- und Mittelgruppen mit ihren Namen und
// allen Adressen, nicht nur denen, die REGOdeploy selbst vergeben hat. Fehlt
// ein Import, bleibt der generierte Katalog aus dem Export die Quelle.
$gaNeu = 0;
$gaVorhanden = 0;
$gaUebersprungen = 0;
$freie = 0;
$ohneDpt = [];

// DPT der Adressen, die einer Funktion gehören -- die schlägt den Wert aus der
// ETS-Datei, weil die Funktion mit ihr arbeitet (etwa 18.001 statt 17.001 bei
// Szenen). Rückmeldungen erben den DPT ihrer Primäradresse.
$eigeneDpt = [];
foreach ($tree['etagen'] as $etage) {
    foreach ($etage['raeume'] as $raum) {
        foreach ($raum['funktionen'] as $funktion) {
            foreach ($funktion['adressen'] as $adresse) {
                $eigeneDpt[$adresse['group_address']] = $adresse['dpt_id'];
                foreach ($adresse['feedback_addresses'] as $fb) {
                    $eigeneDpt[$fb['group_address']] = $adresse['dpt_id'];
                }
            }
        }
    }
}

$katalog = http_get_json(
    "$REGODEPLOY_BASE_URL/api/projects/$REGODEPLOY_PROJECT_ID/knx-projekt-import/gruppenadressen",
    $token
);

if (is_array($katalog) && !empty($katalog)) {
    $knxId = sync_category(KNX_ROOT_IDENT, 'KNX', 1, $rootId, $index, $visited);

    // Nach Haupt- und Mittelgruppe gliedern, Reihenfolge nach Nummer.
    $gegliedert = [];
    foreach ($katalog as $eintrag) {
        $teile = explode('/', $eintrag['address']);
        if (count($teile) !== 3) {
            continue;
        }
        $hg = ($eintrag['hauptgruppe'] === null) ? (int) $teile[0] : (int) $eintrag['hauptgruppe'];
        $mg = ($eintrag['mittelgruppe'] === null) ? (int) $teile[1] : (int) $eintrag['mittelgruppe'];

        $gegliedert[$hg]['name'] = $eintrag['hauptgruppe_name'] ?: ('Hauptgruppe ' . $hg);
        $gegliedert[$hg]['mittelgruppen'][$mg]['name'] = $eintrag['mittelgruppe_name'] ?: ('Mittelgruppe ' . $hg . '/' . $mg);
        $gegliedert[$hg]['mittelgruppen'][$mg]['adressen'][] = $eintrag;
    }
    ksort($gegliedert);

    foreach ($gegliedert as $hg => $hauptgruppe) {
        $hgId = sync_category(HG_PREFIX . $hg, $hauptgruppe['name'], $hg, $knxId, $index, $visited);
        ksort($hauptgruppe['mittelgruppen']);

        foreach ($hauptgruppe['mittelgruppen'] as $mg => $mittelgruppe) {
            $mgId = sync_category(MG_PREFIX . $hg . '_' . $mg, $mittelgruppe['name'], $mg, $hgId, $index, $visited);

            usort($mittelgruppe['adressen'], function ($a, $b) {
                return ((int) explode('/', $a['address'])[2]) <=> ((int) explode('/', $b['address'])[2]);
            });

            foreach ($mittelgruppe['adressen'] as $eintrag) {
                $adresse = $eintrag['address'];
                $dpt = $eigeneDpt[$adresse] ?? $eintrag['dpt'];
                if (!isset($eigeneDpt[$adresse])) {
                    $freie++;
                }
                $position = (int) explode('/', $adresse)[2];

                switch (sync_gruppenadresse($adresse, $eintrag['name'], $dpt, $mgId, $position, $gatewayId, $index, $visited)) {
                    case 'neu':
                        $gaNeu++;
                        break;
                    case 'vorhanden':
                        $gaVorhanden++;
                        break;
                    default:
                        $gaUebersprungen++;
                        $ohneDpt[] = $adresse . ' ' . $eintrag['name'];
                }
            }
        }
    }
} elseif (isset($tree['gruppenadressen'])) {
    // Kein ETS-Import vorhanden: der generierte Katalog des Exports.
    $knxId = sync_category(KNX_ROOT_IDENT, 'KNX', 1, $rootId, $index, $visited);

    foreach ($tree['gruppenadressen'] as $hauptgruppe) {
        $hgId = sync_category(
            HG_PREFIX . $hauptgruppe['hauptgruppe'], $hauptgruppe['name'],
            $hauptgruppe['hauptgruppe'], $knxId, $index, $visited
        );

        foreach ($hauptgruppe['mittelgruppen'] as $mittelgruppe) {
            $mgId = sync_category(
                MG_PREFIX . $hauptgruppe['hauptgruppe'] . '_' . $mittelgruppe['mittelgruppe'],
                $mittelgruppe['name'], $mittelgruppe['mittelgruppe'], $hgId, $index, $visited
            );

            foreach ($mittelgruppe['adressen'] as $adresse) {
                $position = (int) explode('/', $adresse['group_address'])[2];
                switch (sync_gruppenadresse($adresse['group_address'], $adresse['name'], $adresse['dpt_id'],
                                            $mgId, $position, $gatewayId, $index, $visited)) {
                    case 'neu':
                        $gaNeu++;
                        break;
                    case 'vorhanden':
                        $gaVorhanden++;
                        break;
                    default:
                        $gaUebersprungen++;
                }
            }
        }
    }
}

// Die Reihenfolge kommt aus der Reihenfolge des Exports, nicht aus
// "sort_order" -- das steht im Projekt fast überall auf 0, während die Liste
// selbst bereits so sortiert ist, wie REGOdeploy sie zeigt.
foreach ($tree['etagen'] as $etagePosition => $etage) {
    $etageId = sync_category(ETAGE_PREFIX . $etage['id'], $etage['name'], $etagePosition, $visuId, $index, $visited);

    foreach ($etage['raeume'] as $raumPosition => $raum) {
        $raumId = sync_category(RAUM_PREFIX . $raum['id'], $raum['name'], $raumPosition, $etageId, $index, $visited);
        $raeume++;

        foreach ($raum['funktionen'] as $funktionPosition => $funktion) {
            $bezeichnung = $raum['name'] . ' / ' . $funktion['name'];
            $info = $meta[$funktion['id']] ?? null;

            if (($info !== null) && !$info['visu_sichtbar']) {
                $hinweise[] = "$bezeichnung: in REGOdeploy nicht für die Visu freigegeben";
                continue;
            }
            if (empty($funktion['adressen'])) {
                $hinweise[] = "$bezeichnung: in REGOdeploy sind keine Gruppenadressen hinterlegt";
                continue;
            }

            $unterart = ($info['unterart'] ?? '') ?: '';
            $key = ($unterart !== '') && isset(KACHEL_MAPPING[$funktion['funktionstyp'] . '|' . $unterart])
                ? $funktion['funktionstyp'] . '|' . $unterart
                : $funktion['funktionstyp'];

            if (!isset(KACHEL_MAPPING[$key])) {
                $hinweise[] = "$bezeichnung: Funktionstyp '$key' hat kein Bedienelement";
                continue;
            }
            $mapping = KACHEL_MAPPING[$key];

            // Kacheln mit Liste statt Einzelfeldern (Wetterstation).
            if (isset($mapping['liste'])) {
                $zeilen = wetterstation_readings($funktion, $index);
                if (empty($zeilen)) {
                    $hinweise[] = "$bezeichnung: keine der Adressen ist in Symcon aktiv";
                    continue;
                }
                $werte = [];
                $fehlend = [];
                $listenWert = json_encode($zeilen);
            } else {
                $listenWert = null;
            }

            $werte = [];
            $fehlend = [];
            foreach (($listenWert === null) ? $mapping['props'] : [] as $property => $aktionen) {
                $varId = 0;
                if (empty($aktionen)) {
                    // Ohne benannte Aktion nur dann verdrahten, wenn die
                    // Funktion genau eine Adresse hat -- dann ist sie eindeutig.
                    if (count($funktion['adressen']) === 1) {
                        $varId = variable_for_aktion($funktion, $funktion['adressen'][0]['aktion'], $index);
                    }
                }
                $teil = $mapping['teile'][$property] ?? 'wert';
                foreach ($aktionen as $aktion) {
                    $varId = variable_for_aktion($funktion, $aktion, $index, $teil);
                    if ($varId != 0) {
                        break;
                    }
                }
                if ($varId == 0) {
                    $fehlend[] = $property;
                }
                $werte[$property] = $varId;
            }

            if (($listenWert === null) && (count($fehlend) === count($mapping['props']))) {
                $hinweise[] = "$bezeichnung: keine der benötigten Adressen ist in Symcon aktiv";
                continue;
            }

            $kachelIdent = KACHEL_PREFIX . $funktion['id'];
            $visited[$kachelIdent] = true;
            $geaendert = false;
            $neu = false;

            if (isset($index[$kachelIdent])) {
                $kachelId = $index[$kachelIdent]['id'];
                if (IPS_GetInstance($kachelId)['ModuleInfo']['ModuleID'] !== $mapping['module']) {
                    // Funktionstyp geändert -- die alte Kachel passt nicht mehr.
                    IPS_DeleteInstance($kachelId);
                    unset($index[$kachelIdent]);
                } elseif ($index[$kachelIdent]['parent'] !== $raumId) {
                    IPS_SetParent($kachelId, $raumId);
                    $index[$kachelIdent]['parent'] = $raumId;
                    $geaendert = true;
                }
            }

            if (!isset($index[$kachelIdent])) {
                $kachelId = IPS_CreateInstance($mapping['module']);
                IPS_SetIdent($kachelId, $kachelIdent);
                IPS_SetParent($kachelId, $raumId);
                $index[$kachelIdent] = ['id' => $kachelId, 'parent' => $raumId];
                $neu = true;
            }

            $current = json_decode(IPS_GetConfiguration($kachelId), true);
            $apply = false;
            foreach ($werte as $property => $varId) {
                if (($current[$property] ?? 0) !== $varId) {
                    IPS_SetProperty($kachelId, $property, $varId);
                    $apply = true;
                }
            }
            if (($listenWert !== null) && (($current[$mapping['liste']] ?? '') !== $listenWert)) {
                IPS_SetProperty($kachelId, $mapping['liste'], $listenWert);
                $apply = true;
            }
            // Vorbelegung: nur setzen, solange das Feld unberührt ist.
            foreach (($mapping['vorbelegung'] ?? []) as $property => $vorgabe) {
                $bisher = trim((string) ($current[$property] ?? ''));
                if (($bisher === '') || ($bisher === '[]')) {
                    IPS_SetProperty($kachelId, $property, $vorgabe);
                    $apply = true;
                }
            }
            // Feste Vorgaben des Funktionstyps (Nachkommastellen, Texte).
            foreach (($mapping['einstellungen'] ?? []) as $property => $vorgabe) {
                if (!array_key_exists($property, $current) || ($current[$property] != $vorgabe)) {
                    IPS_SetProperty($kachelId, $property, $vorgabe);
                    $apply = true;
                }
            }
            if ($apply) {
                IPS_ApplyChanges($kachelId);
                $geaendert = true;
            }
            if (IPS_GetName($kachelId) !== $funktion['name']) {
                IPS_SetName($kachelId, $funktion['name']);
                $geaendert = true;
            }
            IPS_SetPosition($kachelId, $funktionPosition);
            $kachelIds[] = $kachelId;

            // Verknüpfungen für die Detailseite der Kachel.
            $linkEintraege = [];
            if ($listenWert !== null) {
                foreach ($zeilen as $zeile) {
                    $linkEintraege[$zeile['Label']] = $zeile['VariableID'];
                }
            } else {
                foreach ($werte as $property => $varId) {
                    $linkEintraege[LINK_BESCHRIFTUNG[$property] ?? $property] = $varId;
                }
            }
            $links += sync_links($kachelId, $linkEintraege);

            // Messwerte: aufzeichnen, damit die Visualisierung Verläufe zeigt.
            if ($mapping['module'] === RGV_SENSOR) {
                $messwerte = array_merge($messwerte, array_values($werte));
            } elseif ($mapping['module'] === RGV_WETTER) {
                foreach ($zeilen as $zeile) {
                    $messwerte[] = $zeile['VariableID'];
                    if ($zeile['Label'] === 'Außentemperatur') {
                        $aussentemperatur = $zeile['VariableID'];
                    }
                }
            } elseif ($mapping['module'] === RGV_KLIMA) {
                $messwerte[] = $werte['ActualVariable'] ?? 0;
            }

            if ($neu) {
                $kachelnNeu++;
            } elseif ($geaendert) {
                $kachelnAktualisiert++;
            } else {
                $kachelnUnveraendert++;
            }

            if (!empty($fehlend)) {
                $hinweise[] = "$bezeichnung: Kachel angelegt, aber ohne " . implode(', ', $fehlend);
            }
        }
    }
}

$infoId = sync_infokachel($visuId, $aussentemperatur, $index, $visited);
if ($infoId != 0) {
    $kachelIds[] = $infoId;
}

$aufgezeichnet = aktiviere_aufzeichnung($messwerte);
$nurLesen = setze_nur_lesen($messwerte);

$visuAngepasst = richte_visualisierung_ein($visuId, $kachelIds, $KACHEL_MASSE, $WETTER_MASSE, $GRAPH_ZEITSPANNE);

// Was es im Projekt nicht mehr gibt: einsammeln statt löschen.
$verwaist = [];
foreach ($index as $ident => $info) {
    // Der Verwaist-Ordner selbst entsteht erst weiter unten -- ohne diese
    // Ausnahme wollte er sich in sich selbst einsortieren.
    if (in_array($ident, [DEPLOY_ROOT_IDENT, ORPHAN_ROOT_IDENT, VISU_ROOT_IDENT], true)) {
        continue;
    }
    if (!isset($visited[$ident])) {
        $verwaist[$ident] = $info;
    }
}

$verwaistIds = [];
foreach ($verwaist as $info) {
    $verwaistIds[$info['id']] = true;
}

$verschoben = 0;
$orphanRootId = null;
foreach ($verwaist as $ident => $info) {
    if (isset($verwaistIds[$info['parent']])) {
        // Der Vater ist selbst verwaist und nimmt dieses Objekt mit.
        continue;
    }
    if ($orphanRootId === null) {
        $orphanRootId = sync_category(ORPHAN_ROOT_IDENT, 'REGOdeploy – Verwaist', 2, $rootId, $index, $visited);
    }
    if ($info['parent'] !== $orphanRootId) {
        IPS_SetParent($info['id'], $orphanRootId);
        $verschoben++;
    }
}

echo "REGOvisu-Deploy fertig.\n";
echo "  Modul:    $modulStatus\n";
echo sprintf("  Struktur: %d Räume unter \"Visu %s\"\n", $raeume, $tree['project_name']);
echo sprintf("  Kacheln:  %d neu, %d aktualisiert, %d unverändert, %d Verknüpfungen\n",
    $kachelnNeu, $kachelnAktualisiert, $kachelnUnveraendert, $links);
echo sprintf("  KNX:      %d Adressen neu, %d vorhanden, %d ohne passendes Symcon-Modul; davon %d freie\n",
    $gaNeu, $gaVorhanden, $gaUebersprungen, $freie);
$masseText = [];
foreach ($KACHEL_MASSE as $geraet => $m) {
    $masseText[] = sprintf('%s %dx%d/%dx%d', $geraet,
        $m['quer']['breite'], $m['quer']['hoehe'], $m['hoch']['breite'], $m['hoch']['hoehe']);
}
echo sprintf("  Messwerte:%d Variablen neu in der Aufzeichnung, %d auf nur lesen gestellt%s\n",
    $aufgezeichnet, $nurLesen,
    ($infoId != 0) ? ', Infokachel auf der Startseite' : '');
echo sprintf("  Visu:     %d Visualisierung(en) starten auf \"%s\"; Kacheln quer/hoch: %s\n",
    $visuAngepasst, IPS_GetName($visuId), implode(', ', $masseText));
echo sprintf("  Verwaist: %d Objekte (%d verschoben)\n", count($verwaist), $verschoben);

if (!empty($ohneDpt)) {
    // Ohne Datenpunkttyp laesst sich kein Geraet erzeugen -- nur zaehlen,
    // nicht seitenweise auflisten.
    echo sprintf("\n%d Adressen der ETS haben keinen Datenpunkttyp und bleiben deshalb aussen vor.\n",
        count($ohneDpt));
}

if (!empty($hinweise)) {
    echo "\nHinweise:\n";
    foreach ($hinweise as $zeile) {
        echo "  - $zeile\n";
    }
}
