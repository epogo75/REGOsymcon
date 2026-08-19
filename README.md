# REGOvisu

Symcon-Kacheln im Design von **REGObaseX1** — dieselben Bedienelemente,
dieselbe Palette, nur auf Symcon-Variablen statt auf einem Gira X1.

Jede Kachel ist eine eigene Instanz, die auf vorhandene Symcon-Variablen zeigt.
Das Modul legt keine eigenen Variablen an und schreibt nichts, was nicht über
einen Knopf ausgelöst wurde — es ist reine Darstellung und Bedienung.

Der Funktionsname steht bewusst **nicht** in der Kachel: die
Kachel-Visualisierung schreibt den Instanznamen selbst darüber.

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/kacheln-dunkel.svg">
  <img alt="Die zehn REGOvisu-Kacheln: Schalten, Dimmen, Jalousie, Klima, Szene, Sensor, Wetterstation, Info, Taster, URL-Aufruf" src="docs/kacheln-hell.svg" width="456">
</picture>

Maßstabsgetreu gezeichnet, hell und dunkel — welche Palette eine Kachel nimmt,
entscheidet sie selbst (siehe [Design](#design)). Zum Anfassen liegt dieselbe
Darstellung als Seite bei: [`docs/vorschau.html`](docs/vorschau.html) — im
Browser öffnen, die Regler und Knöpfe reagieren.


## Module

| Modul | In der Kachel |
|---|---|
| REGOvisu Schalten | AN/AUS-Knopf, AN in Rot |
| REGOvisu Dimmen | AN/AUS-Knopf, Prozentregler, Prozentwert |
| REGOvisu Jalousie | Position in %, Auf / Stopp / Ab |
| REGOvisu Klima | „Ist 21,5 °C", Stepper − / Soll / + |
| REGOvisu Szene | Ein Knopf je Szene |
| REGOvisu Sensor | Große Zahl mit Einheit, darunter Pillen für zweiten Zustand und Batterie — reine Anzeige |
| REGOvisu Wetterstation | Werteraster mit Beschriftungen, darunter die Ja/Nein-Meldungen als Pillen |
| REGOvisu Info | Sonnenaufgang, Sonnenuntergang und Außentemperatur — steht auf der Startseite |
| REGOvisu Taster | Ein Knopf, der auslöst — ohne Zustandsanzeige, weil ein Taster keinen hat |
| REGOvisu URL-Aufruf | Ein Knopf, der eine Seite in einem neuen Tab öffnet |

## Installation

Auf einem leeren Symcon genügt **ein** Skript: `tools/regovisu_deploy.php`.
Es installiert die Modulbibliothek selbst und baut danach alles auf:

1. Modulbibliothek über die Modulverwaltung (falls noch nicht da)
2. „Visu &lt;Projekt&gt;" mit Etagen und Räumen
3. den kompletten Adresskatalog des importierten ETS-Projekts unter
   „REGOdeploy > KNX": Haupt- und Mittelgruppen mit ihren ETS-Namen, jede
   Gruppenadresse als eigenes, exakt typisiertes Gerät am KNX Gateway — auch
   die Adressen, die keiner REGOdeploy-Funktion gehören („freie")
4. in jeden Raum die passenden Kacheln, verdrahtet mit den Variablen genau
   dieser Geräte — jede Gruppenadresse existiert damit nur einmal in Symcon
6. eine Infokachel auf der Startseite mit Sonnenauf- und -untergang aus
   Symcons Location-Instanz und der Außentemperatur der Wetterstation
7. Aufzeichnung und Diagramm für alle Messwerte (Sensoren, Wetterstation und
   die Ist-Temperatur der Klimakacheln) über die Archive-Instanz
8. Nur-Lesen für genau diese Messwerte: das KNX-Gerät verliert sein
   Schreibrecht, damit die Visualisierung keine Bedienung anbietet, wo ein
   Sensor nichts entgegennimmt

Etagen, Räume und Kacheln stehen in der Reihenfolge, in der REGOdeploy sie
liefert — nicht nach `sort_order`, das im Projekt fast überall auf 0 steht.

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
| Welche Symcon-Variable ist das? | Das Katalog-Gerät der Adresse (Ident `regodeploy_ga_1_0_0`), dessen Variable `Value` |
| Welcher Datenpunkttyp? | Bei Adressen einer Funktion der DPT der Funktion, sonst der aus der ETS-Datei |
| Welches Symcon-Modul zu einem DPT? | Zur Laufzeit aus Symcons Modulliste (`KNX DPT <n>`), Dimension ist der Nebentyp |

Adressen, denen die ETS keinen Datenpunkttyp gibt, bekommen kein Gerät — ohne
Typ gäbe es nichts zu erzeugen. Das Skript zählt sie und nennt sie am Ende.

| Funktionstyp | Kachel | Aktionen |
|---|---|---|
| `schalten` | Schalten | Schalten, Status |
| `dimmen` | Dimmen | Schalten, Status, Dimmen absolut, Zustandswert |
| `dimmen` / `rgb` | Dimmen | Allgemein Schalten / Status / Helligkeit (+ Rückmeldung) |
| `jalousie` | Jalousie | Move, Step, Move to Position, Position Feedback |
| `temperatur` | Klima | Ist-Temperatur, Soll-Temperatur |
| `klima` | Klima | Ist-Temperatur, Soll-Temperatur (+ Rückmeldung) |
| `szene` | Szene | Szene |
| `sensor` / `temperatur` | Sensor | Temperatur |
| `sensor` / `feuchte` | Sensor | Feuchte |
| `sensor` / `co2` | Sensor | CO2 Wert |
| `sensor` / `fensterkontakt` | Sensor | Fenster offen/geschlossen, gekippt/geschlossen, Batteriestand |
| `sensor` / `rauchmelder` | Sensor | Rauchmelder ausgelöst, Batteriestand |
| `sensor` / `wassermelder` | Sensor | Wassermelder ausgelöst, Batteriestand |
| `sensor` (andere Unterart) | Sensor | die einzige Adresse der Funktion, wenn es genau eine gibt |
| `wetterstation` | Wetterstation | alle Adressen der Funktion, sortiert; Zahlen ins Raster, Ja/Nein in die Pillen |
| `taster` | Taster | Auslösen |
| `url_aufruf` | URL-Aufruf | keine Gruppenadresse — die Adresse kommt aus dem Feld `url` der Funktion |

Rückmeldungen zeigen auf ihre eigene Adresse: bei „Licht — Ein/Aus" schreibt
die Kachel auf die Schaltadresse und liest den Status von der
Rückmeldeadresse. Mehrteilige DPTs (etwa 18.001 für Szenen, mit Nummer und
Aufrufen/Speichern-Bit) sind in `DPT_TEILE` hinterlegt.

Funktionen ohne Häkchen „für die Visu freigegeben", ohne Gruppenadressen oder
ohne passenden Kacheltyp werden übersprungen und am Ende einzeln mit Begründung genannt.

## Detailseite und Verlauf

Unter jeder Kachel legt das Deploy-Skript Verknüpfungen auf die Variablen an,
mit denen sie verdrahtet ist — beschriftet nach ihrer Rolle („Schalten",
„Position Rückmeldung", „Ist-Temperatur"). Die Detailseite der Kachel zeigt
damit die beteiligten Gruppenadressen, jede mit ihrem eigenen Verlauf. Die
Kachel selbst zeichnet nichts.

Nicht mehr verdrahtete Verknüpfungen entfernt das Skript wieder.

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

Der Zuschnitt folgt dem Detail-Dialog von REGObaseX1: Regler oben über die
volle Breite mit großem runden Griff und Prozentwert rechts, darunter gleich
breite Knöpfe. Einen eigenen Rahmen bringt die Kachel nicht mit — Symcons Karte
ist der Rahmen und liefert Raum und Namen.

Schrift ist Inter mit System-Fallback. Das Skript setzt außerdem die Kachelmaße
im Raster, getrennt je Zielgerät und Ausrichtung (`$KACHEL_MASSE`), und die
Startkategorie der Visualisierung auf den Visu-Ordner des Projekts.

## Voraussetzungen

Symcon ab 7.1 (HTML-SDK), entwickelt und getestet auf 9.1.

## Lizenz

MIT
