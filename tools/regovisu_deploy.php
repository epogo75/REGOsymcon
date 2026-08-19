<?php
// REGOvisu-Deploy
//
// Legt zu jeder Funktion eines REGOdeploy-Projekts die passende REGOvisu-
// Kachel an und verdrahtet sie mit den Variablen der KNX-Sammelinstanz, die
// der REGOdeploy-Symcon-Organizer erzeugt hat.
//
// Reihenfolge auf einem frischen Symcon:
//   1. Modul installieren (Modules -> + -> Repository-URL)
//   2. REGOdeploy-Symcon-Organizer laufen lassen  -> Etagen, Räume, KNX-Geräte
//   3. dieses Skript laufen lassen                -> Kacheln je Funktion
//
// Das Skript ist idempotent: erneutes Ausführen zieht Umbenennungen,
// Umzüge und geänderte Adressen nach. Es fasst den Organizer und dessen
// Objekte nicht an und legt selbst keine Variablen an.
//
// Nichts an der Zuordnung ist geraten: welche Aktion zu welchem Bedienelement
// gehört, steht im Funktionenkatalog von REGOdeploy (/api/funktionenkatalog);
// welche Gruppenadresse dahinter liegt, kommt aus dem Projekt-Export; und der
// Variablen-Ident der Sammelinstanz ist die Gruppenadresse selbst
// ("GA_1_0_0_Value"). Rückmeldungen sind im Organizer in ihre Primäradresse
// eingefaltet ("Mapping"), deshalb landen Schreib- und Rückmelde-Eigenschaft
// bewusst auf derselben Variable.

$REGODEPLOY_BASE_URL = 'http://192.168.1.229:8001';
$REGODEPLOY_USERNAME = 'BENUTZER';
$REGODEPLOY_PASSWORD = 'PASSWORT';
$REGODEPLOY_PROJECT_ID = 1;

// Modul-GUIDs der REGOvisu-Bibliothek
const RGV_SCHALTEN = '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}';
const RGV_DIMMEN   = '{A4507CF7-C921-467C-BD01-699C862B9F5C}';
const RGV_JALOUSIE = '{23F455EC-9236-480B-B02F-E10CE43DBDE2}';
const RGV_KLIMA    = '{FEB37553-F02A-4F1B-A669-15BCD71E0712}';
const RGV_SZENE    = '{C2314D3B-F6AD-40E2-B5B7-6DB850E0AD5E}';

const SAMMELINSTANZ_IDENT_PREFIX = 'regodeploy_sammelinstanz_';
const KACHEL_IDENT_PREFIX = 'regovisu_kachel_';

// Funktionstyp (optional "|unterart") -> Modul und Eigenschaften.
//
// Je Eigenschaft stehen die Aktionen in der Reihenfolge, in der sie probiert
// werden; die Namen sind wörtlich die des REGOdeploy-Funktionenkatalogs.
// Mehrere Kandidaten heißt nicht "irgendwas passendes suchen", sondern: die
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
        'timeout' => 30,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    if ($result === false) {
        throw new Exception("GET $url fehlgeschlagen");
    }
    return json_decode($result, true);
}

/**
 * Sucht rekursiv alle Objekte mit einem der übergebenen Ident-Präfixe.
 */
function collect_idents($parentId, $prefixes, &$index)
{
    foreach (IPS_GetChildrenIDs($parentId) as $childId) {
        $obj = IPS_GetObject($childId);
        foreach ($prefixes as $prefix) {
            if (strpos($obj['ObjectIdent'], $prefix) === 0) {
                $index[$obj['ObjectIdent']] = ['id' => $childId, 'parent' => $obj['ParentID']];
                break;
            }
        }
        if ($obj['HasChildren']) {
            collect_idents($childId, $prefixes, $index);
        }
    }
}

/**
 * Aktion -> Variablen-ID in der Sammelinstanz.
 *
 * Die Aktion kann eine Primäradresse sein oder eine Rückmeldung, die der
 * Organizer in ihre Primäradresse eingefaltet hat -- in beiden Fällen ist die
 * Variable die der Primäradresse.
 */
function variable_for_aktion($funktion, $aktion, $sammelinstanzId)
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
        $ident = sprintf('GA_%d_%d_%d_Value', $h, $m, $s);
        $varId = @IPS_GetObjectIDByIdent($ident, $sammelinstanzId);
        return ($varId === false) ? 0 : $varId;
    }
    return 0;
}

// ---- Ablauf ----

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

$sammelIndex = [];
$kachelIndex = [];
collect_idents(0, [SAMMELINSTANZ_IDENT_PREFIX, KACHEL_IDENT_PREFIX], $sammelIndex);
foreach ($sammelIndex as $ident => $info) {
    if (strpos($ident, KACHEL_IDENT_PREFIX) === 0) {
        $kachelIndex[$ident] = $info;
    }
}

$created = 0;
$updated = 0;
$unchanged = 0;
$visited = [];
$skipped = [];

foreach ($tree['etagen'] as $etage) {
    foreach ($etage['raeume'] as $raum) {
        foreach ($raum['funktionen'] as $funktion) {
            $id = $funktion['id'];
            $bezeichnung = $raum['name'] . ' / ' . $funktion['name'];

            $info = $meta[$id] ?? null;
            if (($info !== null) && !$info['visu_sichtbar']) {
                $skipped[] = "$bezeichnung: in REGOdeploy nicht für die Visu freigegeben";
                continue;
            }

            $unterart = ($info['unterart'] ?? '') ?: '';
            $key = ($unterart !== '') && isset(KACHEL_MAPPING[$funktion['funktionstyp'] . '|' . $unterart])
                ? $funktion['funktionstyp'] . '|' . $unterart
                : $funktion['funktionstyp'];

            if (!isset(KACHEL_MAPPING[$key])) {
                $skipped[] = "$bezeichnung: Funktionstyp '$key' hat kein Bedienelement in REGObaseX1";
                continue;
            }
            $mapping = KACHEL_MAPPING[$key];

            if (empty($funktion['adressen'])) {
                $skipped[] = "$bezeichnung: in REGOdeploy sind keine Gruppenadressen hinterlegt";
                continue;
            }

            $sammelIdent = SAMMELINSTANZ_IDENT_PREFIX . $id;
            if (!isset($sammelIndex[$sammelIdent])) {
                $skipped[] = "$bezeichnung: keine KNX-Sammelinstanz -- erst den REGOdeploy-Organizer laufen lassen";
                continue;
            }
            $sammelId = $sammelIndex[$sammelIdent]['id'];
            $raumCatId = IPS_GetObject($sammelId)['ParentID'];

            $werte = [];
            $fehlend = [];
            foreach ($mapping['props'] as $property => $aktionen) {
                $varId = 0;
                foreach ($aktionen as $aktion) {
                    $varId = variable_for_aktion($funktion, $aktion, $sammelId);
                    if ($varId != 0) {
                        break;
                    }
                }
                if ($varId == 0) {
                    $fehlend[] = $property . ' (' . implode(' / ', $aktionen) . ')';
                }
                $werte[$property] = $varId;
            }

            if (count($fehlend) === count($mapping['props'])) {
                $skipped[] = "$bezeichnung: keine der benötigten Adressen ist in Symcon aktiv";
                continue;
            }

            $kachelIdent = KACHEL_IDENT_PREFIX . $id;
            $visited[$kachelIdent] = true;
            $changed = false;

            if (isset($kachelIndex[$kachelIdent])) {
                $kachelId = $kachelIndex[$kachelIdent]['id'];
                $isNew = false;
                if (IPS_GetInstance($kachelId)['ModuleInfo']['ModuleID'] !== $mapping['module']) {
                    // Funktionstyp in REGOdeploy geändert -- die alte Kachel
                    // passt nicht mehr und wird durch die richtige ersetzt.
                    IPS_DeleteInstance($kachelId);
                    unset($kachelIndex[$kachelIdent]);
                } else {
                    if (IPS_GetObject($kachelId)['ParentID'] !== $raumCatId) {
                        IPS_SetParent($kachelId, $raumCatId);
                        $changed = true;
                    }
                }
            }

            if (!isset($kachelIndex[$kachelIdent])) {
                $kachelId = IPS_CreateInstance($mapping['module']);
                IPS_SetIdent($kachelId, $kachelIdent);
                IPS_SetParent($kachelId, $raumCatId);
                $kachelIndex[$kachelIdent] = ['id' => $kachelId, 'parent' => $raumCatId];
                $isNew = true;
            }

            $current = json_decode(IPS_GetConfiguration($kachelId), true);
            $needsApply = false;
            foreach ($werte as $property => $varId) {
                if (($current[$property] ?? 0) !== $varId) {
                    IPS_SetProperty($kachelId, $property, $varId);
                    $needsApply = true;
                }
            }
            if ($needsApply) {
                IPS_ApplyChanges($kachelId);
                $changed = true;
            }

            if (IPS_GetName($kachelId) !== $funktion['name']) {
                IPS_SetName($kachelId, $funktion['name']);
                $changed = true;
            }
            IPS_SetPosition($kachelId, 100 + $funktion['sort_order']);

            if ($isNew) {
                $created++;
            } elseif ($changed) {
                $updated++;
            } else {
                $unchanged++;
            }

            if (!empty($fehlend)) {
                $skipped[] = "$bezeichnung: Kachel angelegt, aber ohne " . implode(', ', $fehlend);
            }
        }
    }
}

// Kacheln zu Funktionen, die es nicht mehr gibt: einsammeln statt löschen,
// gleiche Regel wie beim Organizer.
$verwaist = [];
foreach ($kachelIndex as $ident => $info) {
    if (!isset($visited[$ident])) {
        $verwaist[$ident] = $info;
    }
}

$verwaistVerschoben = 0;
if (!empty($verwaist)) {
    $orphanRootId = @IPS_GetObjectIDByIdent('regodeploy_orphan_root', 0);
    if ($orphanRootId === false) {
        $rootId = @IPS_GetObjectIDByIdent('regodeploy_root', 0);
        if ($rootId !== false) {
            $orphanRootId = @IPS_GetObjectIDByIdent('regodeploy_orphan_root', $rootId);
        }
    }
    if ($orphanRootId !== false) {
        foreach ($verwaist as $info) {
            if ($info['parent'] !== $orphanRootId) {
                IPS_SetParent($info['id'], $orphanRootId);
                $verwaistVerschoben++;
            }
        }
    }
}

echo sprintf(
    "REGOvisu-Deploy fertig: %d Kacheln neu, %d aktualisiert, %d unverändert, %d übersprungen, %d verwaist (%d verschoben).\n",
    $created,
    $updated,
    $unchanged,
    count($skipped),
    count($verwaist),
    $verwaistVerschoben
);

if (!empty($skipped)) {
    echo "\nHinweise:\n";
    foreach ($skipped as $line) {
        echo "  - $line\n";
    }
}
