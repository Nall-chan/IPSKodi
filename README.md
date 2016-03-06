# IPSKodi

Implementierung der Kodi JSON-RPC API in IP-Symcon.

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Vorbereitungen](#4-vorbereitungen)
5. [Einrichten der Instanzen in IPS](#5-einrichten-der--instanzen-in-ips)
6. [PHP-Befehlsreferenz](#6-php-befehlsreferenz) 
7. [Parameter / Modul-Infos](#7-parameter--modul-infos) 
8. [Tips & Tricks](#8-tips--tricks) 
9. [Anhang](#9-anhang)

## 1. Funktionsumfang

 Ermöglicht das Steuern und das empfangen von Statusänderungen, von der Mediacenter-Software Kodi über das Netzwerk.
 Direkte (eingeschränkte) Bedienung im WebFront möglich.
 Abbilden der gesamten Kodi-API in vollen Funktionsumfangs in PHP-Befehlen für eigene Scripte in IPS.


## 2. Voraussetzungen

 - IPS ab Version 4.x
 - Installierte Systeme mit der Software Kodi 


## 3. Installation

**IPS 4.x:**  
   Über das Modul-Control folgende URL hinzufügen.  
   `git://github.com/Nall-chan/IPSKodi.git`  

## 4. Vorbereitungen

 In den Kodi-Systemen folgende Einstellungen vornehmen:

 - In Settings/Services/Remote Control
    - Allow programs on other systems to control Kodi.
 - In Settings/Services/Webserver
    - Allow control of Kodi via HTTP

Aktuell wird eine Authentifizierung des Webservers nicht unterstützt.
Der Zugriff wird außerdem nur für das Anzeigen von Covern, Bannern bzw. Poster benötigt.

## 5. Einrichten der  Instanzen in IPS


## 6. Funktionen der Instanzen

Jeder Typ von Instanz bildet einen bestimmen Funktionsbereich der Kodi-API ab.

 **Kodi Anwendung (KodiDeviceApplication):**  
 RPC-Namensraum : Application
 
 Lautstärke             - Setzen, lesen und visualisieren.  
 Stummschaltung         - Setzen, lesen und visualisieren.  
 Bildschrimschoner      - Status visualisieren.  
 Software beenden       - Nur ausführen.  
 Namen der Software     - Lesen und visualisieren.  
 Version der Software   - Lesen und visualisieren.  
---

 **Kodi Audio Datenbank (KodiDeviceAudioLibrary):**
 RPC-Namensraum : AudioLibrary

 Künstler   - Lesen von Daten aus der Datenbank.  
 Alben      - Lesen von Daten aus der Datenbank.  

Das Setzen von Daten in der Datenbank ist nicht möglich!  

---


## 7. PHP-Befehlsreferenz

 **Kodi Anwendung (KodiDeviceApplication):**  
```php
boolean KODIAPP_SetMute(integer $InstanzeID, boolean $Value;
```
 De-/Aktiviert die Stummschaltung.  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.

```php
boolean KODIAPP_SetVolume(integer $InstanzeID, integer $Value);
```
 Setzen der Lautstärke (0 - 100).  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.

```php
boolean KODIAPP_Quit(integer $InstanzeID);
```
 Beendet die Kodi-Anwendung.  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.

```php
boolean KODIAPP_RequestState(integer $InstanzeID, string $Ident);
```
 Frage einen Wert ab.  
 Es ist der Ident der Statusvariable zu übergeben ("volume","muted","name","version")  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.  

---

 **Kodi Audio Datenbank (KodiDeviceAudioLibrary):**
```php
boolean KODIAUDIOLIB_Scan(integer $InstanzeID);
```
 Startet das bereinigen der Datenbank.  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.  

```php
boolean KODIAUDIOLIB_Clean(integer $InstanzeID);
```
 Startet das bereinigen der Datenbank.  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.  

```php
boolean KODIAUDIOLIB_Export(integer $InstanzeID, string $Path, boolean $Overwrite, boolean $includeImages);
```
 Exportiert die Audio Datenbank.  
 Rückgabewert TRUE bei erfolgreicher Ausführung, sonst FALSE.  

```php
boolean KODIAUDIOLIB_GetAlbumDetails(integer $InstanzeID, integer $AlbumID);
```
 Liest die Eigenschaften eines Album aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  

| Index                     | Typ       | Beschreibung               |
|:-------------------------:|:---------:|:--------------------------:|
| theme                     | string[]  |                            |
| description               | string    | Beschreibung               |
| type                      | string    |                            |
| style                     | string[]  | Array der Stiele           |
| albumid                   | integer   |                            |
| playcount                 | integer   | Anzahl der Wiedergaben     |
| albumlabel                | string    |                            |
| mood                      | string[]  | Array der Stimmungen       |
| displayartist             | string    | Künstler                   |
| artist                    | string[]  | Array der Künstler         |
| genreid                   | integer[] | Array der Genre IDs        |
| musicbrainzalbumartistid  | string    | Music Brainz AlbumArtistID |
| year                      | integer   | Erscheinungsjahr           |
| rating                    | integer   | Bewertung                  |
| artistid                  | integer[] | Array der Künstler IDs     |
| title                     | string    | Titel des Album            |
| musicbrainzalbumid        | string    | Music Brainz AlbumID       |
| genre                     | string[]  | Array der Genres           |
| fanart                    | string    | Pfad zum Fanart            |
| thumbnail                 | string    | Pfad zum Cover             |

```php
boolean KODIAUDIOLIB_GetAlbums(integer $InstanzeID);
```
 Liest einen Teil der Eigenschaften aller Alben aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetAlbumDetails.  

```php
boolean KODIAUDIOLIB_GetRecentlyAddedAlbums(integer $InstanzeID);
```
 Liest die Eigenschaften der Alben aus, welche zuletzt zur Datenbank hinzugefügt wurden.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetAlbumDetails.  

```php
boolean KODIAUDIOLIB_GetRecentlyPlayedAlbums(integer $InstanzeID);
```
 Liest die Eigenschaften der Alben aus, welche zuletzt zur wiedergegeben wurden.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetAlbumDetails.  

```php
boolean KODIAUDIOLIB_GetArtistDetails(integer $InstanzeID, integer $ArtistID);
```
 Liest die Eigenschaften eines Künstlers aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  

| Index                 | Typ       | Beschreibung      |
|:---------------------:|:---------:|:-----------------:|
| born                  | string    |                   |
| formed                | string    |                   |
| died                  | string    |                   |
| style                 | string[]  |                   |
| yearsactive           | string[]  |                   |
| mood                  | string[]  |                   |
| musicbrainzartistid   | string[]  |                   |
| disbanded             | string    |                   |
| description           | string    |                   |
| artist                | string    |                   |
| instrument            | string[]  |                   |
| artistid              | integer   |                   |
| genre                 | string[]  | Array der Genres  |
| fanart                | string    | Pfad zum Fanart   |
| thumbnail             | string    | Pfad zum Cover    |

```php
boolean KODIAUDIOLIB_GetArtists(integer $InstanzeID);
```
 Liest die Eigenschaften aller Künstler aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetArtistDetails.  

```php
boolean KODIAUDIOLIB_GetGenres(integer $InstanzeID);
```
 Liest die Eigenschaften aller bekannten Genres aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 
| Index     | Typ     | Beschreibung    |
|:---------:|:-------:|:---------------:|
| genreid   | integer | ID des Genres   |
| fanart    | string  | Pfad zum Fanart |
| thumbnail | string  | Pfad zum Cover  |

```php
boolean KODIAUDIOLIB_GetSongDetails(integer $InstanzeID, integer $SongID);
```
 Liest die Eigenschaften eines Songs aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  

| Index                     | Typ       | Beschreibung                  |
|:-------------------------:|:---------:|:-----------------------------:|
| lyrics                    | string    |                               |
| songid                    | integer   |                               |
| albumartistid             | integer[] |                               |
| disc                      | integer   |                               |
| comment                   | string    |                               |
| playcount                 | integer   | Anzahl der Wiedergaben        |
| album                     | string    |                               |
| file                      | string    |                               |
| lastplayed                | string    |                               |
| albumid                   | integer   |                               |
| musicbrainzartistid       | string    |                               |
| albumartist               | string[]  |                               |
| duration                  | integer   |                               |
| musicbrainztrackid        | string    |                               |
| track                     | integer   |                               |
| displayartist             | string    | Künstler                      |
| artist                    | string[]  |                               |
| genreid                   | integer[] | Array der Genre IDs           |
| musicbrainzalbumartistid  | string    | Music Brainz AlbumArtistID    |
| year                      | integer   | Erscheinungsjahr              |
| rating                    | integer   | Bewertung                     |
| artistid                  | integer[] | Array der Künstler IDs        |
| title                     | string    | Titel des Album               |
| musicbrainzalbumid        | string    | Music Brainz AlbumID          |
| genre                     | string[]  | Array der Genres              |
| fanart                    | string    | Pfad zum Fanart               |
| thumbnail                 | string    | Pfad zum Cover                |

```php
boolean KODIAUDIOLIB_GetSongs(integer $InstanzeID);
```
 Liest die Eigenschaften aller Songs aus.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetSongDetails.  

```php
boolean GetRecentlyAddedSongs(integer $InstanzeID);
```
 Liest die Eigenschaften der Songs aus, welche zuletzt zur Datenbank hinzugefügt wurden.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetSongDetails.  

```php
boolean GetRecentlyPlayedSongs(integer $InstanzeID);
```
 Liest die Eigenschaften der Songs aus, welche zuletzt zur wiedergegeben wurden.  
 Rückgabewert ist ein assoziertes Array mit den Daten. Tritt ein Fehler auf, wird FALSE zurüchgegeben.  
 Es gilt die Tabelle von KODIAUDIOLIB_GetSongDetails.  

## 8. Parameter / Modul-Infos

GUIDs der Instanzen (z.B. wenn Instanz per PHP angelegt werden soll):  

| Instanz  | GUID                                   |
| :------: | :------------------------------------: |
| KodiDeviceApplication  | {3AF936C4-9B31-48EC-84D8-A30F0BEF104C} |
| KodiDeviceAudioLibrary | {AA078FB4-30C1-4EF1-A2DE-5F957F58BDDC} |

Eigenschaften von KodiDeviceApplication:  

| Eigenschaft | Typ     | Standardwert | Funktion                             |
| :---------: | :-----: | :----------: | :----------------------------------: |
| showName    | boolean | true         | Statusvariable für Name verwenden    |
| showVersion | boolean | true         | Statusvariable für Version verwenden |
| showExit    | boolean | true         | Aktions-Variable für beenden anlegen |

Eigenschaften von KodiDeviceAudioLibrary:  

| Eigenschaft | Typ     | Standardwert | Funktion                                             |
| :---------: | :-----: | :----------: | :--------------------------------------------------: |
| showScan    | boolean | true         | Statusvariable für DB-Scanner verwenden              |
| showDoScan  | boolean | true         | Statusvariable für DB-Bereinigung verwenden          |
| showClean   | boolean | true         | Aktions-Variable zum starten des Scan anlegen        |
| showDoClean | boolean | true         | Aktions-Variable zum starten der Bereinigung anlegen |


## 9. Tips & Tricks

- ...
- ...
- ...

## 10. Anhang

**Changlog:**

0.1	:  Beginn