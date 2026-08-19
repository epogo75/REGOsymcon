# REGOvisu

Symcon-Kacheln im Design von **REGObaseX1** — dieselben Bedienelemente,
dieselbe Palette, nur auf Symcon-Variablen statt auf einem Gira X1.

Jede Kachel ist eine eigene Instanz, die auf vorhandene Symcon-Variablen zeigt.
Das Modul legt keine eigenen Variablen an und schreibt nichts, was nicht über
einen Knopf ausgelöst wurde — es ist reine Darstellung und Bedienung.

Der Funktionsname steht bewusst **nicht** in der Kachel: die
Kachel-Visualisierung schreibt den Instanznamen selbst darüber.

## Module

| Modul | In der Kachel |
|---|---|
| REGOvisu Schalten | AN/AUS-Knopf, AN in Rot |
| REGOvisu Dimmen | AN/AUS-Knopf, Prozentregler, Prozentwert |
| REGOvisu Jalousie | Position in %, Auf / Stopp / Ab |
| REGOvisu Klima | „Ist 21,5 °C", Stepper − / Soll / + |
| REGOvisu Szene | Ein Knopf je Szene |

## Installation

Auf einem leeren Symcon genügt **ein** Skript: `tools/regovisu_deploy.php`.
Es installiert die Modulbibliothek selbst und baut danach alles auf:

1. Modulbibliothek über die Modulverwaltung (falls noch nicht da)
2. „Visu &lt;Projekt&gt;" mit Etagen und Räumen
3. je Funktion ein KNX-Gerät unter „REGOdeploy > KNX-Geräte", verbunden mit
   dem KNX Gateway
4. in jeden Raum die passenden Kacheln, verdrahtet mit den Variablen dieser
   Geräte
5. den vollständigen ETS-Adresskatalog unter „REGOdeploy > KNX"
   (abschaltbar über `$MIT_ADRESSKATALOG`)

In den Räumen stehen damit nur die Kacheln; alles Technische liegt darunter in
„REGOdeploy".

Erneutes Ausführen ist sicher: Umbenennungen, Umzüge und geänderte Adressen
werden nachgezogen, Objekte zu gelöschten Funktionen landen unter
„REGOdeploy – Verwaist" statt gelöscht zu werden.

### Aus REGOdeploy heraus

Das Skript ist zugleich die Push-Vorlage von REGOdeploy
(`backend/regodeploy/symcon/organizer_script.php`). Die fünf `CHANGE_ME`-Zeilen
am Kopf füllt REGOdeploy beim Übertragen aus — deren Schreibweise darf sich
deshalb nicht ändern. „Skript übertragen" und „Skript ausführen" in REGOdeploy
erledigen damit den kompletten Aufbau.

Ohne REGOdeploy trägt man die fünf Werte von Hand ein; die Gateway-Instanz
findet das Skript notfalls selbst.

## Zuordnung

Nichts daran ist geraten:

| Frage | Quelle |
|---|---|
| Welche Aktion gehört zu welchem Bedienelement? | Funktionenkatalog von REGOdeploy (`/api/funktionenkatalog`) |
| Welche Gruppenadresse steckt dahinter? | Projekt-Export (`/export/symcon-tree`) |
| Welche Symcon-Variable ist das? | Ident des KNX-Geräts ist die Gruppenadresse: `GA_1_0_0_Value` |

| Funktionstyp | Kachel | Aktionen |
|---|---|---|
| `schalten` | Schalten | Schalten, Status |
| `dimmen` | Dimmen | Schalten, Status, Dimmen absolut, Zustandswert |
| `dimmen` / `rgb` | Dimmen | Allgemein Schalten / Status / Helligkeit (+ Rückmeldung) |
| `jalousie` | Jalousie | Move, Step, Move to Position, Position Feedback |
| `temperatur` | Klima | Ist-Temperatur, Soll-Temperatur |
| `klima` | Klima | Ist-Temperatur, Soll-Temperatur (+ Rückmeldung) |
| `szene` | Szene | Szene |

Rückmeldeadressen faltet das Skript in ihre Primäradresse ein, deshalb zeigen
Schreib- und Rückmelde-Eigenschaft bewusst auf dieselbe Variable.

Funktionen ohne Häkchen „für die Visu freigegeben", ohne Gruppenadressen oder
mit einem Typ ohne Bedienelement (`sensor`, `wetterstation`, `taster`,
`url_aufruf`) werden übersprungen und am Ende einzeln mit Begründung genannt.

## Design

| Rolle | Hell | Dunkel |
|---|---|---|
| Kachelfläche | `#ffffff` | `#131316` |
| Rahmen | `rgba(0,0,0,.08)` | `rgba(255,255,255,.06)` |
| Text | `#1a1a1c` | `#f2f2f4` |
| Akzent | `#4d7616` | `#b9ff5c` |
| Eingeschaltet | `#cf222e` | `#f85149` |

Hell oder dunkel entscheidet die Kachel selbst: die Kachel-Visualisierung hängt
ihre Farben als Query-Parameter an das iframe, aus der Helligkeit von
`cardcolor` leitet das Modul das Theme ab. Die Kachel folgt damit Symcon und
nicht dem Betriebssystem.

Schrift ist Inter mit System-Fallback, Grundgröße 12 px. Die Zeile wächst mit
ihrem Inhalt und füllt den Kachelplatz nicht aus.

## Voraussetzungen

Symcon ab 7.1 (HTML-SDK), entwickelt und getestet auf 9.1.

## Lizenz

MIT
