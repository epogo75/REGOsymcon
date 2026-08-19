<?php
// REGOvisu-Deploy -- ein Skript, das auf einem leeren Symcon alles aufbaut.
//
//   1. installiert die REGOvisu-Modulbibliothek, falls sie fehlt
//   2. "Visu <Projekt>" mit Etagen und Räumen
//   3. je Funktion ein KNX-Gerät unter "REGOdeploy > KNX-Geräte",
//      verbunden mit dem KNX Gateway
//   4. in jeden Raum die passenden REGOvisu-Kacheln, verdrahtet mit den
//      Variablen dieser KNX-Geräte
//   5. den vollständigen ETS-Adresskatalog unter "REGOdeploy > KNX",
//      Hauptgruppe/Mittelgruppe wie in der ETS, jede Adresse als eigenes,
//      exakt typisiertes Gerät
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
    'Desktop' => ['quer' => ['breite' => 6,  'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
    'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
    'Tablet'  => ['quer' => ['breite' => 6,  'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
];

// Den vollständigen ETS-Adresskatalog mit anlegen? Er wird für die Kacheln
// nicht gebraucht, ist aber praktisch, um immer alles im Projekt zu haben.
$MIT_ADRESSKATALOG = true;

const REGOVISU_REPOSITORY = 'https://github.com/epogo75/SymconREGOvisu';

// Modul-GUIDs der REGOvisu-Bibliothek
const RGV_SCHALTEN = '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}';
const RGV_DIMMEN   = '{A4507CF7-C921-467C-BD01-699C862B9F5C}';
const RGV_JALOUSIE = '{23F455EC-9236-480B-B02F-E10CE43DBDE2}';
const RGV_KLIMA    = '{FEB37553-F02A-4F1B-A669-15BCD71E0712}';
const RGV_SZENE    = '{C2314D3B-F6AD-40E2-B5B7-6DB850E0AD5E}';

const MODULE_CONTROL_GUID = '{B8A5067A-AFC2-3798-FEDC-BCD02A45615E}';
const TILE_VISU_GUID      = '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}';
const KNX_GATEWAY_GUID    = '{1C902193-B044-43B8-9433-419F09C641B8}';
const KNX_DEVICE_GUID     = '{FB223058-3084-C5D0-C7A2-3B8D2E73FE8A}';

const VISU_ROOT_IDENT   = 'regodeploy_visu_root';
const DEPLOY_ROOT_IDENT = 'regodeploy_root';
const ORPHAN_ROOT_IDENT = 'regodeploy_orphan_root';
const KNX_ROOT_IDENT    = 'regodeploy_knx_root';
const KNX_GERAETE_IDENT = 'regovisu_knx_geraete';

const ETAGE_PREFIX   = 'regodeploy_etage_';
const RAUM_PREFIX    = 'regodeploy_raum_';
const GERAET_PREFIX  = 'regodeploy_sammelinstanz_';
const HG_PREFIX      = 'regodeploy_hg_';
const MG_PREFIX      = 'regodeploy_mg_';
const GA_PREFIX      = 'regodeploy_ga_';
const KACHEL_PREFIX  = 'regovisu_kachel_';

const VERWALTETE_PREFIXE = [
    ETAGE_PREFIX, RAUM_PREFIX, GERAET_PREFIX, HG_PREFIX, MG_PREFIX, GA_PREFIX, KACHEL_PREFIX,
];
const VERWALTETE_IDENTS = [
    VISU_ROOT_IDENT, DEPLOY_ROOT_IDENT, ORPHAN_ROOT_IDENT, KNX_ROOT_IDENT, KNX_GERAETE_IDENT,
];

// Rein kosmetische Zuordnung für die "Tag"-Spalte des KNX-Geräts; sie steuert
// nur Symcons eigene Filter und Symbole.
const FUNKTIONSTYP_TAGS = [
    'schalten'   => 'lighting',
    'dimmen'     => 'lighting',
    'jalousie'   => 'shading',
    'temperatur' => 'heating',
    'klima'      => 'heating',
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

function funktionstyp_tag($funktionstyp)
{
    return FUNKTIONSTYP_TAGS[$funktionstyp] ?? 'unknown';
}

/**
 * Eine GroupAddresses-Zeile je Primäradresse; Rückmeldungen werden als
 * "Mapping" in ihre Primäradresse eingefaltet. Type/Dimension sind Haupt- und
 * Nebennummer der DPT, so wie das KNX-Gerät sie erwartet.
 */
function build_group_addresses($funktion)
{
    $rows = [];
    foreach ($funktion['adressen'] as $adresse) {
        $parts = explode('.', (string) $adresse['dpt_id'], 2);
        if ((count($parts) !== 2) || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            continue;
        }
        list($h, $m, $s) = array_map('intval', explode('/', $adresse['group_address']));

        $mapping = [];
        foreach ($adresse['feedback_addresses'] as $fb) {
            list($fh, $fm, $fs) = array_map('intval', explode('/', $fb['group_address']));
            $mapping[] = ['Address1' => $fh, 'Address2' => $fm, 'Address3' => $fs];
        }

        $rows[] = [
            'Address1' => $h, 'Address2' => $m, 'Address3' => $s,
            'Type' => (int) $parts[0], 'Dimension' => (int) $parts[1],
            'Tag' => funktionstyp_tag($funktion['funktionstyp']),
            'SubTag' => (strpos($adresse['aktion'], 'ammellenposition') !== false) ? 'lamella' : '',
            'InitialName' => $adresse['aktion'],
            'Mapping' => $mapping,
            'CapabilityRead' => false, 'CapabilityWrite' => true, 'CapabilityReceive' => true,
            'CapabilityTransmit' => false, 'EmulateStatus' => true,
        ];
    }
    return $rows;
}

/**
 * Das KNX-Gerät einer Funktion: alle aktiven Adressen in einer Instanz.
 */
function sync_geraet($funktion, $parentId, $gatewayId, &$index, &$visited)
{
    $rows = build_group_addresses($funktion);
    if (empty($rows)) {
        return [0, 'leer'];
    }

    $ident = GERAET_PREFIX . $funktion['id'];
    $visited[$ident] = true;
    $desired = json_encode($rows);
    $neu = false;

    if (isset($index[$ident])) {
        $id = $index[$ident]['id'];
        if ($index[$ident]['parent'] !== $parentId) {
            IPS_SetParent($id, $parentId);
            $index[$ident]['parent'] = $parentId;
        }
    } else {
        $id = IPS_CreateInstance(KNX_DEVICE_GUID);
        IPS_SetIdent($id, $ident);
        IPS_SetParent($id, $parentId);
        $index[$ident] = ['id' => $id, 'parent' => $parentId];
        $neu = true;
    }

    $current = json_decode(IPS_GetConfiguration($id), true);
    if (($current['GroupAddresses'] ?? '[]') !== $desired) {
        IPS_SetProperty($id, 'GroupAddresses', $desired);
        IPS_ApplyChanges($id);
    }
    if (IPS_GetName($id) !== $funktion['name']) {
        IPS_SetName($id, $funktion['name']);
    }
    if (IPS_GetInstance($id)['ConnectionID'] !== $gatewayId) {
        IPS_ConnectInstance($id, $gatewayId);
    }

    return [$id, $neu ? 'neu' : 'vorhanden'];
}

/**
 * Eine einzelne Gruppenadresse des ETS-Katalogs als exakt typisiertes Gerät.
 */
function sync_gruppenadresse($adresse, $parentId, $gatewayId, &$index, &$visited)
{
    if (($adresse['symcon_module_id'] === null) || ($adresse['symcon_dimension'] === null)) {
        return 'übersprungen';
    }

    list($h, $m, $s) = array_map('intval', explode('/', $adresse['group_address']));
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
        $id = IPS_CreateInstance($adresse['symcon_module_id']);
        IPS_SetProperty($id, 'Address1', $h);
        IPS_SetProperty($id, 'Address2', $m);
        IPS_SetProperty($id, 'Address3', $s);
        IPS_SetProperty($id, 'Dimension', $adresse['symcon_dimension']);
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

    if (IPS_GetName($id) !== $adresse['name']) {
        IPS_SetName($id, $adresse['name']);
    }
    if (IPS_GetInstance($id)['ConnectionID'] !== $gatewayId) {
        IPS_ConnectInstance($id, $gatewayId);
    }

    return $neu ? 'neu' : 'vorhanden';
}

/**
 * Aktion -> Variablen-ID im KNX-Gerät.
 *
 * Die Aktion kann eine Primäradresse sein oder eine eingefaltete Rückmeldung;
 * in beiden Fällen ist die Variable die der Primäradresse.
 */
function variable_for_aktion($funktion, $aktion, $geraetId)
{
    foreach ($funktion['adressen'] as $adresse) {
        $treffer = ($adresse['aktion'] === $aktion);
        if (!$treffer) {
            foreach ($adresse['feedback_addresses'] as $fb) {
                if ($fb['aktion'] === $aktion) {
                    $treffer = true;
                    break;
                }
            }
        }
        if (!$treffer) {
            continue;
        }

        list($h, $m, $s) = array_map('intval', explode('/', $adresse['group_address']));
        $varId = @IPS_GetObjectIDByIdent(sprintf('GA_%d_%d_%d_Value', $h, $m, $s), $geraetId);
        return ($varId === false) ? 0 : $varId;
    }
    return 0;
}

/**
 * Richtet die Kachel-Visualisierung ein: sie startet im Visu-Ordner des
 * Projekts (dessen Etagen sind damit die erste Ebene), und jede REGOvisu-
 * Kachel bekommt die Höhe einer Zeile statt der vollen Standardhöhe.
 *
 * Die Kachelkonfiguration ist eine Map "Name -> Konfiguration"; bestehende
 * Konfigurationen bleiben erhalten, nur die Maße der eigenen Kacheln werden
 * gesetzt.
 */
function richte_visualisierung_ein($visuRootId, $kachelIds, $alleMasse)
{
    $angepasst = 0;

    foreach (IPS_GetInstanceListByModuleID(TILE_VISU_GUID) as $visId) {
        if (json_decode(IPS_GetConfiguration($visId), true)['BaseID'] !== $visuRootId) {
            IPS_SetProperty($visId, 'BaseID', $visuRootId);
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
            $config = is_string($eintrag['Config']) ? json_decode($eintrag['Config'], true) : $eintrag['Config'];
            if (!is_array($config)) {
                continue;
            }
            foreach (['landscape', 'portrait'] as $lage) {
                if (!isset($config[$lage]) || (($config[$lage]['crossAxis'] ?? null) === null)) {
                    continue;
                }
                $spalten = $config[$lage]['crossAxis'];
                $fuerLage = $masse[($lage === 'landscape') ? 'quer' : 'hoch'];
                $masseFuerKachel = [
                    'height' => max(1, (int) $fuerLage['hoehe']),
                    'width' => max(1, min((int) $fuerLage['breite'], $spalten)),
                ];
                $dim = (array) ($config[$lage]['individualDimensions'] ?? []);
                foreach ($kachelIds as $id) {
                    $dim[(string) $id] = $masseFuerKachel;
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

$visuId    = sync_category(VISU_ROOT_IDENT, 'Visu ' . $tree['project_name'], 0, 0, $index, $visited);
$rootId    = sync_category(DEPLOY_ROOT_IDENT, 'REGOdeploy', 1, 0, $index, $visited);
$geraeteId = sync_category(KNX_GERAETE_IDENT, 'KNX-Geräte', 0, $rootId, $index, $visited);

// Das laufende Skript legt sich selbst in den REGOdeploy-Ordner.
if (isset($_IPS['SELF']) && ($_IPS['SELF'] > 0) && IPS_ObjectExists($_IPS['SELF'])
    && (IPS_GetObject($_IPS['SELF'])['ParentID'] !== $rootId)) {
    IPS_SetParent($_IPS['SELF'], $rootId);
}

$kachelIds = [];
$kachelnNeu = 0;
$kachelnAktualisiert = 0;
$kachelnUnveraendert = 0;
$geraeteNeu = 0;
$geraeteVorhanden = 0;
$raeume = 0;
$hinweise = [];

foreach ($tree['etagen'] as $etage) {
    $etageId = sync_category(ETAGE_PREFIX . $etage['id'], $etage['name'], $etage['sort_order'], $visuId, $index, $visited);

    foreach ($etage['raeume'] as $raum) {
        $raumId = sync_category(RAUM_PREFIX . $raum['id'], $raum['name'], $raum['sort_order'], $etageId, $index, $visited);
        $raeume++;

        foreach ($raum['funktionen'] as $funktion) {
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

            list($geraetId, $geraetStatus) = sync_geraet($funktion, $geraeteId, $gatewayId, $index, $visited);
            if ($geraetId == 0) {
                $hinweise[] = "$bezeichnung: keine Adresse mit gültiger DPT";
                continue;
            }
            if ($geraetStatus === 'neu') {
                $geraeteNeu++;
            } else {
                $geraeteVorhanden++;
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

            $werte = [];
            $fehlend = [];
            foreach ($mapping['props'] as $property => $aktionen) {
                $varId = 0;
                foreach ($aktionen as $aktion) {
                    $varId = variable_for_aktion($funktion, $aktion, $geraetId);
                    if ($varId != 0) {
                        break;
                    }
                }
                if ($varId == 0) {
                    $fehlend[] = $property;
                }
                $werte[$property] = $varId;
            }

            if (count($fehlend) === count($mapping['props'])) {
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
            if ($apply) {
                IPS_ApplyChanges($kachelId);
                $geaendert = true;
            }
            if (IPS_GetName($kachelId) !== $funktion['name']) {
                IPS_SetName($kachelId, $funktion['name']);
                $geaendert = true;
            }
            IPS_SetPosition($kachelId, $funktion['sort_order']);
            $kachelIds[] = $kachelId;

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

// Vollständiger ETS-Adresskatalog
$gaNeu = 0;
$gaVorhanden = 0;
$gaUebersprungen = 0;

if ($MIT_ADRESSKATALOG && isset($tree['gruppenadressen'])) {
    $knxId = sync_category(KNX_ROOT_IDENT, 'KNX', 1, $rootId, $index, $visited);

    foreach ($tree['gruppenadressen'] as $hauptgruppe) {
        $hgId = sync_category(
            HG_PREFIX . $hauptgruppe['hauptgruppe'],
            $hauptgruppe['name'],
            $hauptgruppe['hauptgruppe'],
            $knxId,
            $index,
            $visited
        );

        foreach ($hauptgruppe['mittelgruppen'] as $mittelgruppe) {
            $mgId = sync_category(
                MG_PREFIX . $hauptgruppe['hauptgruppe'] . '_' . $mittelgruppe['mittelgruppe'],
                $mittelgruppe['name'],
                $mittelgruppe['mittelgruppe'],
                $hgId,
                $index,
                $visited
            );

            foreach ($mittelgruppe['adressen'] as $adresse) {
                switch (sync_gruppenadresse($adresse, $mgId, $gatewayId, $index, $visited)) {
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

$visuAngepasst = richte_visualisierung_ein($visuId, $kachelIds, $KACHEL_MASSE);

// Was es im Projekt nicht mehr gibt: einsammeln statt löschen.
$verwaist = [];
foreach ($index as $ident => $info) {
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
echo sprintf("  KNX:      %d Geräte neu, %d vorhanden\n", $geraeteNeu, $geraeteVorhanden);
echo sprintf("  Kacheln:  %d neu, %d aktualisiert, %d unverändert\n",
    $kachelnNeu, $kachelnAktualisiert, $kachelnUnveraendert);
if ($MIT_ADRESSKATALOG) {
    echo sprintf("  Katalog:  %d Adressen neu, %d vorhanden, %d ohne Symcon-Zuordnung\n",
        $gaNeu, $gaVorhanden, $gaUebersprungen);
}
$masseText = [];
foreach ($KACHEL_MASSE as $geraet => $m) {
    $masseText[] = sprintf('%s %dx%d/%dx%d', $geraet,
        $m['quer']['breite'], $m['quer']['hoehe'], $m['hoch']['breite'], $m['hoch']['hoehe']);
}
echo sprintf("  Visu:     %d Visualisierung(en) starten auf \"%s\"; Kacheln quer/hoch: %s\n",
    $visuAngepasst, IPS_GetName($visuId), implode(', ', $masseText));
echo sprintf("  Verwaist: %d Objekte (%d verschoben)\n", count($verwaist), $verschoben);

if (!empty($hinweise)) {
    echo "\nHinweise:\n";
    foreach ($hinweise as $zeile) {
        echo "  - $zeile\n";
    }
}
