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

// Zeitspanne der Diagramme in der Visualisierung:
// 0 Stunde, 1 Tag, 2 Woche, 3 Monat, 4 Jahr, 5 Jahrzehnt.
$GRAPH_ZEITSPANNE = 0;

// Kacheln, die mehr als eine Bedienzeile zeigen, brauchen mehr Platz als eine
// gewöhnliche -- je Modul eigene Maße, gleicher Aufbau wie oben. Was hier
// nicht steht, bekommt $KACHEL_MASSE.
$SONDER_MASSE = [
    // Wetterstation: Werteraster plus Meldungen
    '{6EFCE386-425F-4AF5-8440-B93CAA0B3C2E}' => [
        'Desktop' => ['quer' => ['breite' => 6,  'hoehe' => 3], 'hoch' => ['breite' => 6, 'hoehe' => 3]],
        'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 5], 'hoch' => ['breite' => 6, 'hoehe' => 5]],
        'Tablet'  => ['quer' => ['breite' => 12, 'hoehe' => 4], 'hoch' => ['breite' => 6, 'hoehe' => 4]],
    ],
    // Info: vier Felder nebeneinander. Auf dem Rechner so hoch wie die
    // Zaehlerkarte, damit beide in einer Reihe gleich hoch stehen.
    '{63561319-730C-4139-8F95-1DA3BD142C83}' => [
        'Desktop' => ['quer' => ['breite' => 6,  'hoehe' => 3], 'hoch' => ['breite' => 6, 'hoehe' => 3]],
        'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 3], 'hoch' => ['breite' => 6, 'hoehe' => 3]],
        'Tablet'  => ['quer' => ['breite' => 12, 'hoehe' => 2], 'hoch' => ['breite' => 6, 'hoehe' => 2]],
    ],
    // Zähler: Leistung, Zählerstand, Heute und die übrigen Messwerte
    '{A9DEE307-F3B2-48A2-9960-245799F8BBD9}' => [
        'Desktop' => ['quer' => ['breite' => 6,  'hoehe' => 3], 'hoch' => ['breite' => 6, 'hoehe' => 3]],
        'Phone'   => ['quer' => ['breite' => 12, 'hoehe' => 5], 'hoch' => ['breite' => 6, 'hoehe' => 5]],
        'Tablet'  => ['quer' => ['breite' => 12, 'hoehe' => 4], 'hoch' => ['breite' => 6, 'hoehe' => 4]],
    ],
];

const REGOVISU_REPOSITORY = 'https://github.com/epogo75/REGOsymcon';
// So heißt die Bibliothek in Symcons Modulverwaltung (der Ordnername).
const REGOVISU_BIBLIOTHEK = 'REGOvisu';

// Modul-GUIDs der REGOvisu-Bibliothek
const RGV_SCHALTEN = '{E5F57876-C2BE-4C9B-9D1E-237D9010ADA8}';
const RGV_DIMMEN   = '{A4507CF7-C921-467C-BD01-699C862B9F5C}';
const RGV_JALOUSIE = '{23F455EC-9236-480B-B02F-E10CE43DBDE2}';
const RGV_KLIMA    = '{FEB37553-F02A-4F1B-A669-15BCD71E0712}';
const RGV_SZENE    = '{C2314D3B-F6AD-40E2-B5B7-6DB850E0AD5E}';
const RGV_SENSOR   = '{0871A6F2-8912-4EC0-9C4F-616982DAFF34}';
const RGV_WETTER   = '{6EFCE386-425F-4AF5-8440-B93CAA0B3C2E}';
const RGV_INFO     = '{63561319-730C-4139-8F95-1DA3BD142C83}';
const RGV_TASTER   = '{2562CE14-21C3-4609-B04E-A9A69C51C684}';
const RGV_URL      = '{E72DF487-94FC-4942-8473-1FBEAF87564B}';
const RGV_ZAEHLER  = '{A9DEE307-F3B2-48A2-9960-245799F8BBD9}';

const CLIENT_SOCKET_GUID  = '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}';
const MODBUS_GATEWAY_GUID = '{A5F663AB-C400-4FE5-B207-4D67CC030564}';
const MODBUS_ADDRESS_GUID = '{CB197E50-273D-4535-8C91-BB35273E3CA5}';

// Register-Map des Finder-7M-Energiezählers, Block "MEASUREMENTS (IEEE 754)".
// Die Offsets sind Float-Indizes innerhalb ihres Blocks, die Registeradresse
// ist also Blockstart + Offset * 2. Übernommen aus REGObase
// (modbus/registers.py), das sie seinerseits aus dem Datenblatt transkribiert.
//
// "aktiv" entscheidet, was Symcon tatsächlich pollt -- Nachrüsten ist ein
// true statt false, die ganze Map steht schon hier.
const FINDER_BLOCK_A = 2490;
const FINDER_BLOCK_B = 2530;
const FINDER_BLOCK_C = 2570;
const FINDER_BLOCK_D = 2638;

const FINDER_MESSGROESSEN = [
    // Schlüssel => [Block, Offset, Beschriftung, Einheit, Nachkommastellen, Profil, aktiv, Faktor, Gruppe]
    // Zeilen mit gleicher Gruppe stehen in der Kachel zusammen in einem Feld
    // ("232,3 / 232,5 / 230,5 V") statt jede in einem eigenen.
    // Der Faktor ist Symcons eigene Umrechnung in der Adress-Instanz
    // (0 = keine); die Energiezähler liefern Wh, angezeigt wird kWh.
    'wirkleistung'        => [FINDER_BLOCK_A,  0, 'Wirkleistung',            'W',    0, '~Watt',   true],
    'blindleistung'       => [FINDER_BLOCK_A,  1, 'Blindleistung',           'var',  0, '',        false],
    'scheinleistung'      => [FINDER_BLOCK_A,  2, 'Scheinleistung',          'VA',   0, '',        false],
    'leistungsfaktor'     => [FINDER_BLOCK_A,  3, 'Leistungsfaktor',         '',     2, '',        true],
    'frequenz'            => [FINDER_BLOCK_A,  4, 'Frequenz',                'Hz',   2, '',        true],
    'spannung_l1'         => [FINDER_BLOCK_A,  5, 'Spannung L1',             'V',    1, '~Volt',   true,  0, 'Spannung'],
    'spannung_l2'         => [FINDER_BLOCK_A,  6, 'Spannung L2',             'V',    1, '~Volt',   true,  0, 'Spannung'],
    'spannung_l3'         => [FINDER_BLOCK_A,  7, 'Spannung L3',             'V',    1, '~Volt',   true,  0, 'Spannung'],
    'spannung_mittel_ln'  => [FINDER_BLOCK_A,  8, 'Spannung Mittel L-N',     'V',    1, '~Volt',   false],
    'spannung_u12'        => [FINDER_BLOCK_A,  9, 'Spannung L1-L2',          'V',    1, '~Volt',   false],
    'spannung_u23'        => [FINDER_BLOCK_A, 10, 'Spannung L2-L3',          'V',    1, '~Volt',   false],
    'spannung_u31'        => [FINDER_BLOCK_A, 11, 'Spannung L3-L1',          'V',    1, '~Volt',   false],
    'spannung_mittel_ll'  => [FINDER_BLOCK_A, 12, 'Spannung Mittel L-L',     'V',    1, '~Volt',   false],
    'strom_l1'            => [FINDER_BLOCK_A, 13, 'Strom L1',                'A',    2, '~Ampere', true,  0, 'Strom'],
    'strom_l2'            => [FINDER_BLOCK_A, 14, 'Strom L2',                'A',    2, '~Ampere', true,  0, 'Strom'],
    'strom_l3'            => [FINDER_BLOCK_A, 15, 'Strom L3',                'A',    2, '~Ampere', true,  0, 'Strom'],
    'strom_summe'         => [FINDER_BLOCK_A, 16, 'Strom Summe',             'A',    2, '~Ampere', false],
    'strom_mittel'        => [FINDER_BLOCK_A, 19, 'Strom Mittel',            'A',    2, '~Ampere', false],

    'wirkleistung_l1'     => [FINDER_BLOCK_B,  0, 'Wirkleistung L1',         'W',    0, '~Watt',   false],
    'wirkleistung_l2'     => [FINDER_BLOCK_B,  1, 'Wirkleistung L2',         'W',    0, '~Watt',   false],
    'wirkleistung_l3'     => [FINDER_BLOCK_B,  2, 'Wirkleistung L3',         'W',    0, '~Watt',   false],
    'blindleistung_l1'    => [FINDER_BLOCK_B,  4, 'Blindleistung L1',        'var',  0, '',        false],
    'blindleistung_l2'    => [FINDER_BLOCK_B,  5, 'Blindleistung L2',        'var',  0, '',        false],
    'blindleistung_l3'    => [FINDER_BLOCK_B,  6, 'Blindleistung L3',        'var',  0, '',        false],
    'scheinleistung_l1'   => [FINDER_BLOCK_B,  8, 'Scheinleistung L1',       'VA',   0, '',        false],
    'scheinleistung_l2'   => [FINDER_BLOCK_B,  9, 'Scheinleistung L2',       'VA',   0, '',        false],
    'scheinleistung_l3'   => [FINDER_BLOCK_B, 10, 'Scheinleistung L3',       'VA',   0, '',        false],
    'leistungsfaktor_l1'  => [FINDER_BLOCK_B, 12, 'Leistungsfaktor L1',      '',     2, '',        false],
    'leistungsfaktor_l2'  => [FINDER_BLOCK_B, 13, 'Leistungsfaktor L2',      '',     2, '',        false],
    'leistungsfaktor_l3'  => [FINDER_BLOCK_B, 14, 'Leistungsfaktor L3',      '',     2, '',        false],

    'winkel_i1u1'         => [FINDER_BLOCK_C,  0, 'Winkel I1-U1',            '°',    1, '',        false],
    'winkel_i2u2'         => [FINDER_BLOCK_C,  1, 'Winkel I2-U2',            '°',    1, '',        false],
    'winkel_i3u3'         => [FINDER_BLOCK_C,  2, 'Winkel I3-U3',            '°',    1, '',        false],
    'winkel_leistung'     => [FINDER_BLOCK_C,  3, 'Leistungswinkel',         '°',    1, '',        false],
    'winkel_u12'          => [FINDER_BLOCK_C,  4, 'Winkel U12',              '°',    1, '',        false],
    'winkel_u23'          => [FINDER_BLOCK_C,  5, 'Winkel U23',              '°',    1, '',        false],
    'winkel_u31'          => [FINDER_BLOCK_C,  6, 'Winkel U31',              '°',    1, '',        false],
    'thd_i1'              => [FINDER_BLOCK_C,  9, 'Klirrfaktor I1',          '%',    1, '',        false],
    'thd_i2'              => [FINDER_BLOCK_C, 10, 'Klirrfaktor I2',          '%',    1, '',        false],
    'thd_i3'              => [FINDER_BLOCK_C, 11, 'Klirrfaktor I3',          '%',    1, '',        false],
    'thd_u1'              => [FINDER_BLOCK_C, 12, 'Klirrfaktor U1',          '%',    1, '',        false],
    'thd_u2'              => [FINDER_BLOCK_C, 13, 'Klirrfaktor U2',          '%',    1, '',        false],
    'thd_u3'              => [FINDER_BLOCK_C, 14, 'Klirrfaktor U3',          '%',    1, '',        false],

    'energie_bezug'       => [FINDER_BLOCK_D,  0, 'Wirkenergie Bezug',       'kWh',  1, '',        true,  0.001],
    'blindenergie_bezug'  => [FINDER_BLOCK_D,  1, 'Blindenergie Bezug',      'kvarh',1, '',        false, 0.001],
    'energie_einspeisung' => [FINDER_BLOCK_D,  2, 'Wirkenergie Einspeisung', 'kWh',  1, '',        false, 0.001],
    'blindenergie_eins'   => [FINDER_BLOCK_D,  3, 'Blindenergie Einspeisung','kvarh',1, '',        false, 0.001],
    'temperatur_intern'   => [FINDER_BLOCK_D, 10, 'Temperatur im Gerät',     '°C',   1, '~Temperature', false],
];

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
const MODBUS_PREFIX  = 'regovisu_modbus_';
const MODBUS_ROOT_IDENT = 'regovisu_modbus_root';
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
    MODBUS_PREFIX,
];
const VERWALTETE_IDENTS = [
    VISU_ROOT_IDENT, DEPLOY_ROOT_IDENT, ORPHAN_ROOT_IDENT, KNX_ROOT_IDENT, KNX_GERAETE_IDENT,
    INFO_IDENT, MODBUS_ROOT_IDENT,
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

    'taster' => [
        'module' => RGV_TASTER,
        'props' => [
            'TriggerVariable' => ['Auslösen'],
        ],
    ],

    // Der URL-Aufruf hat keine Gruppenadresse, sondern nur eine Adresse im
    // Netz -- die steht in der Funktionsliste, nicht im Symcon-Export.
    'url_aufruf' => [
        'module' => RGV_URL,
        'props' => [],
        'ohne_adressen' => true,
        'aus_meta' => ['Url' => 'url'],
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
    $control = IPS_GetInstanceListByModuleID(MODULE_CONTROL_GUID);
    if (empty($control)) {
        throw new Exception('Keine Modulverwaltung gefunden -- Modul bitte von Hand installieren');
    }

    if (@IPS_GetModule(RGV_SCHALTEN) !== false) {
        // Schon da -- aber liegt auf GitHub etwas Neueres, wird es geholt.
        if (in_array(REGOVISU_BIBLIOTHEK, MC_GetModuleList($control[0]), true)
            && MC_IsModuleUpdateAvailable($control[0], REGOVISU_BIBLIOTHEK)) {
            MC_UpdateModule($control[0], REGOVISU_BIBLIOTHEK);
            $stand = MC_GetModuleRepositoryInfo($control[0], REGOVISU_BIBLIOTHEK);
            return 'aktualisiert auf ' . ($stand['ModuleCommit'] ?? 'neuen Stand');
        }
        return 'war schon installiert und aktuell';
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
function sync_gruppenadresse($adresse, $name, $dptId, $parentId, $position, $gatewayId, &$index, &$visited, $rueckmeldungen = [])
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

    // Rückmeldung: die Statusadresse gehört unter "Mehr?" in die Liste der
    // Feedback Addresses des Befehlsgeräts. Ohne sie kennt das Gerät nur, was
    // es selbst gesendet hat -- ein Aktor, der von Hand oder über eine andere
    // Adresse geschaltet wurde, bliebe unbemerkt.
    $mapping = [];
    foreach ($rueckmeldungen as $fb) {
        list($fh, $fm, $fs) = array_map('intval', explode('/', $fb));
        $mapping[] = ['Address1' => $fh, 'Address2' => $fm, 'Address3' => $fs];
    }
    $mappingJson = json_encode($mapping);
    if (json_decode(IPS_GetProperty($id, 'Mapping'), true) !== $mapping) {
        IPS_SetProperty($id, 'Mapping', $mappingJson);
        IPS_ApplyChanges($id);
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
function richte_visualisierung_ein($visuRootId, $kachelIds, $alleMasse, $sonderMasse, $zeitspanne)
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
                $dim = (array) ($config[$lage]['individualDimensions'] ?? []);
                foreach ($kachelIds as $id) {
                    $eigen = $masseFuerKachel;
                    $modul = IPS_GetInstance($id)['ModuleInfo']['ModuleID'];
                    if (isset($sonderMasse[$modul][$geraet][$schluessel])) {
                        $sonder = $sonderMasse[$modul][$geraet][$schluessel];
                        $eigen = [
                            'height' => max(1, (int) $sonder['hoehe']),
                            'width' => max(1, min((int) $sonder['breite'], $spalten)),
                        ];
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

/**
 * Legt einen Modbus-Energiezähler an: Client Socket, ModBus Gateway und je
 * aktiver Messgröße eine Adress-Instanz.
 *
 * Der Zähler spricht Modbus RTU über TCP (RTU-Rahmen mit CRC über eine
 * TCP-Verbindung, nicht Modbus TCP) und liefert die Messwerte als
 * Big-Endian-Floats über Funktionscode 4 (Read Input Registers) -- so liest
 * REGObase ihn auch. Die Registeradresse ist Blockstart + Offset * 2, weil
 * die Offsets Float-Indizes sind.
 */
function sync_modbus_zaehler($zaehler, $technikId, $kachelParentId, &$index, &$visited)
{
    $schluessel = preg_replace('/[^a-z0-9]+/', '_', strtolower(
        iconv('UTF-8', 'ASCII//TRANSLIT', $zaehler['name'])
    ));

    // Verbindung
    $socketIdent = MODBUS_PREFIX . $schluessel . '_socket';
    $visited[$socketIdent] = true;
    if (isset($index[$socketIdent])) {
        $socketId = $index[$socketIdent]['id'];
        if ($index[$socketIdent]['parent'] !== $technikId) {
            IPS_SetParent($socketId, $technikId);
        }
    } else {
        $socketId = IPS_CreateInstance(CLIENT_SOCKET_GUID);
        IPS_SetIdent($socketId, $socketIdent);
        IPS_SetParent($socketId, $technikId);
        $index[$socketIdent] = ['id' => $socketId, 'parent' => $technikId];
    }
    $vorher = json_decode(IPS_GetConfiguration($socketId), true);
    if (($vorher['Host'] !== $zaehler['host']) || ($vorher['Port'] !== $zaehler['port']) || !$vorher['Open']) {
        IPS_SetProperty($socketId, 'Host', $zaehler['host']);
        IPS_SetProperty($socketId, 'Port', $zaehler['port']);
        IPS_SetProperty($socketId, 'Open', true);
        IPS_ApplyChanges($socketId);
    }
    IPS_SetName($socketId, $zaehler['name'] . ' Verbindung');

    // Gateway
    $gatewayIdent = MODBUS_PREFIX . $schluessel . '_gateway';
    $visited[$gatewayIdent] = true;
    if (isset($index[$gatewayIdent])) {
        $gatewayId = $index[$gatewayIdent]['id'];
        if ($index[$gatewayIdent]['parent'] !== $technikId) {
            IPS_SetParent($gatewayId, $technikId);
        }
    } else {
        $gatewayId = IPS_CreateInstance(MODBUS_GATEWAY_GUID);
        IPS_SetIdent($gatewayId, $gatewayIdent);
        IPS_SetParent($gatewayId, $technikId);
        $index[$gatewayIdent] = ['id' => $gatewayId, 'parent' => $technikId];
    }
    // Blockweise lesen statt je Adresse einzeln: auf einem RTU-zu-TCP-Gateway
    // laufen zehn gleichzeitige Einzelabfragen durcheinander, Antworten landen
    // dann bei der falschen Adresse (gemessen: Frequenz zeigte den Wert der
    // Spannung L1). REGObase liest aus demselben Grund Bloecke mit Pause.
    $bloecke = [];
    foreach ([FINDER_BLOCK_A => 40, FINDER_BLOCK_B => 40, FINDER_BLOCK_C => 30, FINDER_BLOCK_D => 22] as $start => $anzahl) {
        $gebraucht = false;
        foreach (FINDER_MESSGROESSEN as $m) {
            if (($m[0] === $start) && $m[6]) {
                $gebraucht = true;
                break;
            }
        }
        if ($gebraucht) {
            $bloecke[] = ['Function' => 4, 'Address' => $start, 'Quantity' => $anzahl, 'Poller' => $zaehler['poller']];
        }
    }

    $vorher = json_decode(IPS_GetConfiguration($gatewayId), true);
    $soll = [
        'GatewayMode' => 2,
        'DeviceID' => $zaehler['slave'],
        'SwapWords' => false,
        'DelayTimeRTU' => 50,
        'DataBlocks' => json_encode($bloecke),
    ];
    $aendern = false;
    foreach ($soll as $eigenschaft => $wert) {
        if (($vorher[$eigenschaft] ?? null) != $wert) {
            IPS_SetProperty($gatewayId, $eigenschaft, $wert);
            $aendern = true;
        }
    }
    if ($aendern) {
        IPS_ApplyChanges($gatewayId);
    }
    if (IPS_GetInstance($gatewayId)['ConnectionID'] !== $socketId) {
        IPS_ConnectInstance($gatewayId, $socketId);
    }
    IPS_SetName($gatewayId, $zaehler['name'] . ' Modbus');

    // Messgrößen
    $zeilen = [];
    $variablen = [];
    $leistungId = 0;
    $energieId = 0;
    $position = 0;

    foreach (FINDER_MESSGROESSEN as $key => $messgroesse) {
        list($block, $offset, $beschriftung, $einheit, $stellen, $profil, $aktiv) = $messgroesse;
        $faktor = $messgroesse[7] ?? 0;
        $gruppe = $messgroesse[8] ?? '';
        $ident = MODBUS_PREFIX . $schluessel . '_' . $key;
        if (!$aktiv) {
            continue;
        }
        $visited[$ident] = true;

        if (isset($index[$ident])) {
            $adresseId = $index[$ident]['id'];
            if ($index[$ident]['parent'] !== $technikId) {
                IPS_SetParent($adresseId, $technikId);
            }
        } else {
            $adresseId = IPS_CreateInstance(MODBUS_ADDRESS_GUID);
            IPS_SetIdent($adresseId, $ident);
            IPS_SetParent($adresseId, $technikId);
            $index[$ident] = ['id' => $adresseId, 'parent' => $technikId];
        }

        $soll = [
            'ReadFunctionCode' => 4,          // Read Input Registers
            'ReadAddress' => $block + ($offset * 2),
            'DataType' => 7,                  // FLOAT32
            'ByteOrder' => 0,                 // Big-Endian
            'WriteFunctionCode' => 0,         // nur lesen
            'Factor' => $faktor,
            // 0 = nicht selbst pollen, der Wert kommt aus dem Block des Gateways
            'Poller' => 0,
        ];
        $vorher = json_decode(IPS_GetConfiguration($adresseId), true);
        $aendern = false;
        foreach ($soll as $eigenschaft => $wert) {
            if (($vorher[$eigenschaft] ?? null) != $wert) {
                IPS_SetProperty($adresseId, $eigenschaft, $wert);
                $aendern = true;
            }
        }
        if ($aendern) {
            IPS_ApplyChanges($adresseId);
        }
        if (IPS_GetInstance($adresseId)['ConnectionID'] !== $gatewayId) {
            IPS_ConnectInstance($adresseId, $gatewayId);
        }
        IPS_SetName($adresseId, $beschriftung);
        IPS_SetPosition($adresseId, $position++);

        // Die Variable des Moduls heißt "Value"; Profil nur setzen, wenn eins
        // hinterlegt ist -- sonst zeigt die Kachel die Einheit selbst.
        $varId = @IPS_GetObjectIDByIdent('Value', $adresseId);
        if ($varId !== false) {
            if (($profil !== '') && (IPS_GetVariable($varId)['VariableCustomProfile'] !== $profil)
                && IPS_VariableProfileExists($profil)) {
                IPS_SetVariableCustomProfile($varId, $profil);
            }
            IPS_SetName($varId, $beschriftung);
            // Leistung und Zählerstand stehen als eigene Felder vorn, wie in
            // der Zähler-Karte von REGObase; der Rest folgt in der Liste.
            if ($key === 'wirkleistung') {
                $leistungId = $varId;
            } elseif ($key === 'energie_bezug') {
                $energieId = $varId;
            } else {
                // In einer Gruppenzeile stehen drei Felder nebeneinander --
                // "Spannung L1" waere dort abgeschnitten, also nur "L1"; die
                // Einheit steht ohnehin im Wert.
                $kurz = ($gruppe !== '') ? trim(str_replace($gruppe, '', $beschriftung)) : $beschriftung;
                $zeilen[] = [
                    'VariableID' => $varId,
                    'Label' => ($kurz !== '') ? $kurz : $beschriftung,
                    'Group' => $gruppe,
                    'Unit' => $einheit,
                    'Digits' => $stellen,
                ];
            }
            $variablen[$beschriftung] = $varId;
        }
    }

    // Kachel
    $kachelIdent = MODBUS_PREFIX . $schluessel . '_kachel';
    $visited[$kachelIdent] = true;
    if (isset($index[$kachelIdent])) {
        $kachelId = $index[$kachelIdent]['id'];
        if ($index[$kachelIdent]['parent'] !== $kachelParentId) {
            IPS_SetParent($kachelId, $kachelParentId);
        }
    } else {
        $kachelId = IPS_CreateInstance(RGV_ZAEHLER);
        IPS_SetIdent($kachelId, $kachelIdent);
        IPS_SetParent($kachelId, $kachelParentId);
        $index[$kachelIdent] = ['id' => $kachelId, 'parent' => $kachelParentId];
    }
    $vorher = json_decode(IPS_GetConfiguration($kachelId), true);
    $desired = json_encode($zeilen);
    $aendern = false;
    foreach (['PowerVariable' => $leistungId, 'EnergyVariable' => $energieId] as $eigenschaft => $wert) {
        if (($vorher[$eigenschaft] ?? 0) !== $wert) {
            IPS_SetProperty($kachelId, $eigenschaft, $wert);
            $aendern = true;
        }
    }
    if (($vorher['Readings'] ?? '') !== $desired) {
        IPS_SetProperty($kachelId, 'Readings', $desired);
        $aendern = true;
    }
    if ($aendern) {
        IPS_ApplyChanges($kachelId);
    }
    IPS_SetName($kachelId, $zaehler['name']);

    sync_links($kachelId, $variablen);

    return ['kachel' => $kachelId, 'variablen' => array_values($variablen)];
}

/**
 * Symcons Sprache setzen, falls noch keine gewählt ist.
 *
 * Beschriftungen in Symcons eigenen Profilen sind intern englisch ("Off",
 * "On") und werden erst beim Anzeigen übersetzt. Serverseitig -- also auch in
 * der Mitgliederliste einer Szene -- klappt das nur mit gesetzter Sprache; auf
 * einem frischen Symcon steht sie leer und es bliebe bei "Off". Eine bereits
 * gewählte Sprache bleibt unangetastet.
 */
function stelle_sprache_ein(): string
{
    $aktuell = (string) @IPS_GetOption('Locale');
    if ($aktuell !== '') {
        return $aktuell;
    }
    @IPS_SetOption('Locale', 'de_DE');
    return 'de_DE (gesetzt, wirkt nach Neustart)';
}

// ---- Ablauf ----

$sprache = stelle_sprache_ein();
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
$rueckmeldungen = [];
foreach ($tree['etagen'] as $etage) {
    foreach ($etage['raeume'] as $raum) {
        foreach ($raum['funktionen'] as $funktion) {
            foreach ($funktion['adressen'] as $adresse) {
                $eigeneDpt[$adresse['group_address']] = $adresse['dpt_id'];
                foreach ($adresse['feedback_addresses'] as $fb) {
                    $eigeneDpt[$fb['group_address']] = $adresse['dpt_id'];
                    $rueckmeldungen[$adresse['group_address']][] = $fb['group_address'];
                }
            }
        }
    }
}

// Modbus-Energiezähler: Host, Port und Slave-ID stehen in REGOdeploy unter
// Symcon > Modbus, nicht mehr im Skript.
$MODBUS_ZAEHLER = [];
foreach ((array) http_get_json("$REGODEPLOY_BASE_URL/api/projects/$REGODEPLOY_PROJECT_ID/symcon/modbus", $token) as $eintrag) {
    if (!is_array($eintrag) || !isset($eintrag['host'])) {
        continue;
    }
    $MODBUS_ZAEHLER[] = [
        'name'    => $eintrag['name'],
        'host'    => $eintrag['host'],
        'port'    => (int) $eintrag['port'],
        'slave'   => (int) $eintrag['slave_id'],
        'poller'  => (int) $eintrag['poller_ms'],
        'raum_id' => $eintrag['raum_id'],
    ];
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

                switch (sync_gruppenadresse($adresse, $eintrag['name'], $dpt, $mgId, $position, $gatewayId,
                                            $index, $visited, $rueckmeldungen[$adresse] ?? [])) {
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
                                            $mgId, $position, $gatewayId, $index, $visited,
                                            $rueckmeldungen[$adresse['group_address']] ?? [])) {
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
            $unterart = ($info['unterart'] ?? '') ?: '';
            $key = ($unterart !== '') && isset(KACHEL_MAPPING[$funktion['funktionstyp'] . '|' . $unterart])
                ? $funktion['funktionstyp'] . '|' . $unterart
                : $funktion['funktionstyp'];
            $ohneAdressen = KACHEL_MAPPING[$key]['ohne_adressen'] ?? false;

            if (empty($funktion['adressen']) && !$ohneAdressen) {
                $hinweise[] = "$bezeichnung: in REGOdeploy sind keine Gruppenadressen hinterlegt";
                continue;
            }

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

            // Kacheln ohne Gruppenadressen (URL-Aufruf) trifft diese Pruefung nicht.
            if (($listenWert === null) && !empty($mapping['props'])
                && (count($fehlend) === count($mapping['props']))) {
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
            foreach (($mapping['aus_meta'] ?? []) as $property => $feld) {
                $wert = (string) ($info[$feld] ?? '');
                if (($current[$property] ?? '') !== $wert) {
                    IPS_SetProperty($kachelId, $property, $wert);
                    $apply = true;
                }
            }
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

// Modbus-Zähler: Technik unter "REGOdeploy > Modbus", Kachel im Raum.
$zaehlerKacheln = 0;
if (!empty($MODBUS_ZAEHLER)) {
    $modbusId = sync_category(MODBUS_ROOT_IDENT, 'Modbus', 2, $rootId, $index, $visited);

    foreach ($MODBUS_ZAEHLER as $zaehler) {
        $raumIdent = RAUM_PREFIX . (int) ($zaehler['raum_id'] ?? 0);
        $ziel = isset($index[$raumIdent]) ? $index[$raumIdent]['id'] : $visuId;

        $ergebnis = sync_modbus_zaehler($zaehler, $modbusId, $ziel, $index, $visited);
        $kachelIds[] = $ergebnis['kachel'];
        $messwerte = array_merge($messwerte, $ergebnis['variablen']);
        $zaehlerKacheln++;
    }
}

$infoId = sync_infokachel($visuId, $aussentemperatur, $index, $visited);
if ($infoId != 0) {
    $kachelIds[] = $infoId;
}

$aufgezeichnet = aktiviere_aufzeichnung($messwerte);
$nurLesen = setze_nur_lesen($messwerte);

$visuAngepasst = richte_visualisierung_ein($visuId, $kachelIds, $KACHEL_MASSE, $SONDER_MASSE, $GRAPH_ZEITSPANNE);

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

echo "Sprache: $sprache\n";
echo "REGOvisu-Deploy fertig.\n";
echo "  Modul:    $modulStatus\n";
echo sprintf("  Struktur: %d Räume unter \"Visu %s\"\n", $raeume, $tree['project_name']);
echo sprintf("  Kacheln:  %d neu, %d aktualisiert, %d unverändert, %d Verknüpfungen\n",
    $kachelnNeu, $kachelnAktualisiert, $kachelnUnveraendert, $links);
echo sprintf("  KNX:      %d Adressen neu, %d vorhanden, %d ohne passendes Symcon-Modul; davon %d freie\n",
    $gaNeu, $gaVorhanden, $gaUebersprungen, $freie);
$mitRueckmeldung = 0;
foreach ($rueckmeldungen as $befehl => $fbs) {
    list($h, $m, $sub) = array_map('intval', explode('/', $befehl));
    if (isset($index[sprintf('%s%d_%d_%d', GA_PREFIX, $h, $m, $sub)])) {
        $mitRueckmeldung += count($fbs);
    }
}
echo sprintf("  Rückmeldung: %d Statusadressen unter \"Mehr?\" eingetragen\n", $mitRueckmeldung);
$masseText = [];
foreach ($KACHEL_MASSE as $geraet => $m) {
    $masseText[] = sprintf('%s %dx%d/%dx%d', $geraet,
        $m['quer']['breite'], $m['quer']['hoehe'], $m['hoch']['breite'], $m['hoch']['hoehe']);
}
if ($zaehlerKacheln > 0) {
    echo sprintf("  Modbus:   %d Zähler mit %d Messgrößen\n", $zaehlerKacheln,
        count(array_filter(FINDER_MESSGROESSEN, fn($m) => $m[6])));
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
