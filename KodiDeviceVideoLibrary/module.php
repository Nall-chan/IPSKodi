<?

require_once(__DIR__ . "/../KodiClass.php");  // diverse Klassen
/*
 * @addtogroup kodi
 * @{
 *
 * @package       Kodi
 * @file          module.php
 * @author        Michael Tröger
 *
 */

/**
 * KodiDeviceVideoLibrary Klasse für den Namespace VideoLibrary der KODI-API.
 * Erweitert KodiBase.
 *
 */
class KodiDeviceVideoLibrary extends KodiBase
{

    /**
     * RPC-Namespace
     * 
     * @access private
     *  @var string
     * @value 'VideoLibrary'
     */
    static $Namespace = 'VideoLibrary';

    /**
     * Alle Properties des RPC-Namespace
     * 
     * @access private
     *  @var array 
     */
    static $Properties = array(
    );

    /**
     * Alle Eigenschaften von Episoden.
     * 
     * @access private
     *  @var array 
     */
    static $EpisodeItemList = array(
        "title",
        "plot",
        "votes",
        "rating",
        "writer",
        "firstaired",
        "playcount",
        "runtime",
        "director",
        "productioncode",
        "season",
        "episode",
        "originaltitle",
        "showtitle",
        "cast",
        "streamdetails",
        "lastplayed",
        "fanart",
        "thumbnail",
        "file",
        "resume",
        "tvshowid",
        "dateadded",
        "uniqueid",
        "art"
    );

    /**
     * Alle Eigenschaften von Filmen.
     * 
     * @access private
     *  @var array 
     */
    static $MovieItemList = array(
        "title",
        "genre",
        "year",
        "rating",
        "director",
        "trailer",
        "tagline",
        "plot",
        "plotoutline",
        "originaltitle",
        "lastplayed",
        "playcount",
        "writer",
        "studio",
        "mpaa",
        "cast",
        "country",
        "imdbnumber",
        "runtime",
        "set",
        "showlink",
        "streamdetails",
        "top250",
        "votes",
        "fanart",
        "thumbnail",
        "file",
        "sorttitle",
        "resume",
        "setid",
        "dateadded",
        "tag",
        "art");

    /**
     * Alle Eigenschaften von Filmsets.
     * 
     * @access private
     *  @var array 
     */
    static $SetItemList = array(
        "title",
        "playcount",
        "fanart",
        "thumbnail",
        "art"
    );

    /**
     * Alle Eigenschaften von Seasons.
     * 
     * @access private
     *  @var array 
     */
    static $SeasonItemList = array(
        "season",
        "showtitle",
        "playcount",
        "episode",
        "fanart",
        "thumbnail",
        "tvshowid",
        "watchedepisodes",
        "art"
    );

    /**
     * Alle Eigenschaften von Musikvideos.
     * 
     * @access private
     *  @var array 
     */
    static $MusicVideoItemList = array(
        "title",
        "playcount",
        "runtime",
        "director",
        "studio",
        "year",
        "plot",
        "album",
        "artist",
        "genre",
        "track",
        "streamdetails",
        "lastplayed",
        "fanart",
        "thumbnail",
        "file",
        "resume",
        "dateadded",
        "tag",
        "art"
    );

    /**
     * Alle Eigenschaften von TV-Serien.
     * 
     * @access private
     *  @var array 
     */
    static $TvShowItemList = array(
        "title",
        "genre",
        "year",
        "rating",
        "plot",
        "studio",
        "mpaa",
        "cast",
        "playcount",
        "episode",
        "imdbnumber",
        "premiered",
        "votes",
        "lastplayed",
        "fanart",
        "thumbnail",
        "file",
        "originaltitle",
        "sorttitle",
        "episodeguide",
        "season",
        "watchedepisodes",
        "dateadded",
        "tag",
        "art"
    );

    /**
     * Alle Eigenschaften von Genres.
     * 
     * @access private
     *  @var array 
     */
    static $GenreItemList = array(
        "thumbnail",
        "title"
    );

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyBoolean("showDoScan", true);
        $this->RegisterPropertyBoolean("showDoClean", true);
        $this->RegisterPropertyBoolean("showScan", true);
        $this->RegisterPropertyBoolean("showClean", true);
    }

    /**
     * Interne Funktion des SDK.
     * 
     * @access public
     */
    public function ApplyChanges()
    {
        $this->RegisterProfileIntegerEx("Action.Kodi", "", "", "", Array(
            Array(0, "Ausführen", "", -1)
        ));

        if ($this->ReadPropertyBoolean('showDoScan'))
        {
            $this->RegisterVariableInteger("doscan", "Suche nach neuen / veränderten Inhalten", "Action.Kodi", 1);
            $this->EnableAction("doscan");
        }
        else
            $this->UnregisterVariable("doscan");

        if ($this->ReadPropertyBoolean('showScan'))
            $this->RegisterVariableBoolean("scan", "Datenbanksuche läuft", "~Switch", 2);
        else
            $this->UnregisterVariable("scan");

        if ($this->ReadPropertyBoolean('showDoClean'))
        {
            $this->RegisterVariableInteger("doclean", "Bereinigen der Datenbank", "Action.Kodi", 3);
            $this->EnableAction("doclean");
        }
        else
            $this->UnregisterVariable("doclean");

        if ($this->ReadPropertyBoolean('showClean'))
            $this->RegisterVariableBoolean("clean", "Bereinigung der Datenbank läuft", "~Switch", 4);
        else
            $this->UnregisterVariable("clean");

        parent::ApplyChanges();
    }

################## PRIVATE     

    protected function Decode($Method, $KodiPayload)
    {
        switch ($Method)
        {
            case "OnScanStarted":
                $this->SetValueBoolean("scan", true);
                break;
            case "OnScanFinished":
                $this->SetValueBoolean("scan", false);
                break;
            case "OnCleanStarted":
                $this->SetValueBoolean("clean", true);
                break;
            case "OnCleanFinished":
                $this->SetValueBoolean("clean", false);
                break;
        }
    }

################## ActionHandler

    /**
     * Actionhandler der Statusvariablen. Interne SDK-Funktion.
     * 
     * @access public
     * @param string $Ident Der Ident der Statusvariable.
     * @param boolean|float|integer|string $Value Der angeforderte neue Wert.
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "doclean":
                if ($this->Clean() === false)
                    trigger_error('Error start cleaning', E_USER_NOTICE);
                break;
            case "doscan":
                if ($this->Scan() === false)
                    trigger_error('Error start scanning', E_USER_NOTICE);
                break;

            default:
                trigger_error('Invalid Ident.', E_USER_NOTICE);
        }
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'KODIVIDOLIB_Clean'. Startet das bereinigen der Datenbank
     *
     * @access public
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function Clean()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->Clean();
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === "OK";
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDOLIB_Export'. Exportiert die Audio Datenbank.
     *
     * @access public
     * @param  string $Path Ziel-Verzeichnis für den Export.
     * @param boolean $Overwrite Vorhandene Daten überschreiben.
     * @param boolean $includeImages Bilder mit exportieren.
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function Export(string $Path, boolean $Overwrite, boolean $includeImages)
    {
        if (!is_string($Path) or ( strlen($Path) < 2))
        {
            trigger_error('Path is invalid', E_USER_NOTICE);
            return false;
        }
        if (!is_bool($Overwrite))
        {
            trigger_error('Overwrite must be boolean', E_USER_NOTICE);
            return false;
        }
        if (!is_bool($includeImages))
        {
            trigger_error('includeImages must be boolean', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->Export(array("options" => array("path" => $Path, "overwrite" => $Overwrite, "images" => $includeImages)));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === "OK";
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetEpisodeDetails'. Liest die Eigenschaften eines Künstlers aus.
     *
     * @access public
     * @param  integer $EpisodeId EpisodenID der zu lesenden Episode.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetEpisodeDetails(integer $EpisodeId)
    {
        if (!is_int($EpisodeId))
        {
            trigger_error('EpisodeId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetEpisodeDetails(array("episodeid" => $EpisodeId, "properties" => static::$EpisodeItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->episodedetails), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetEpisodes'. Liest die Eigenschaften aller Songs aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetEpisodes()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetEpisodes(array("properties" => static::$EpisodeItemList));
        
        $ret = $this->SendDirect($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->episodes), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetGenres'. Liest die Eigenschaften aller Genres aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetGenres()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetGenres(array("properties" => static::$GenreItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->genres), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMovieDetails'. Liest die Eigenschaften eines Films aus.
     *
     * @access public
     * @param  integer $MovieId MovieID des zu lesenden Films.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMovieDetails(integer $MovieId)
    {
        if (!is_int($MovieId))
        {
            trigger_error('MovieId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetEpisodeDetails(array("movieid" => $MovieId, "properties" => static::$MovieItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->moviedetails), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMovies'. Liest die Eigenschaften aller Filme aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMovies()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetMovies(array("properties" => static::$MovieItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->movies), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMovieSetDetails'. Liest die Eigenschaften eines Film-Sets aus.
     *
     * @access public
     * @param  integer $SetId SetId des zu lesenden Film-Sets.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMovieSetDetails(integer $SetId)
    {
        if (!is_int($SetId))
        {
            trigger_error('SetId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetMovieSetDetails(array("setid" => $SetId, "properties" => static::SetItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->setdetails), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMovieSets'. Liest die Eigenschaften aller Film-Sets.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMovieSets()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetMovieSets(array("properties" => static::$SetItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->sets), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMusicVideoDetails'. Liest die Eigenschaften eines Musikvideos aus.
     *
     * @access public
     * @param  integer $MusicVideoId MusicVideoId des zu lesenden Musikvideos.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMusicVideoDetails(integer $MusicVideoId)
    {
        if (!is_int($MusicVideoId))
        {
            trigger_error('MusicVideoId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetMusicVideoDetails(array("musicvideoid" => $MusicVideoId, "properties" => static::$MusicVideoItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->musicvideodetails), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetMusicVideos'. Liest die Eigenschaften aller Musikvideos.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetMusicVideos()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetMusicVideos(array("properties" => static::$MusicVideoItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->musicvideos), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetRecentlyAddedEpisodes'. Liest die Eigenschaften der zuletzt hinzugefügten Episoden aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetRecentlyAddedEpisodes()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetRecentlyAddedEpisodes(array("properties" => static::$EpisodeItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->episodes), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetRecentlyAddedMovies'. Liest die Eigenschaften der zuletzt hinzugefügten Filme aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetRecentlyAddedMovies()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetRecentlyAddedMovies(array("properties" => static::$MovieItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->movies), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetRecentlyAddedMusicVideos'. Liest die Eigenschaften der zuletzt hinzugefügten Filme aus.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetRecentlyAddedMusicVideos()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetRecentlyAddedMusicVideos(array("properties" => static::$MusicVideoItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->musicvideos), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetSeasons'. Liest die Eigenschaften eines Musikvideos aus.
     *
     * @access public
     * @param  integer $TvShowId TvShowId der zu lesenden Serie.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetSeasons(integer $TvShowId)
    {
        if (!is_int($TvShowId))
        {
            trigger_error('TvShowId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetSeasons(array("tvshowid" => $TvShowId, "properties" => static::$SeasonItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->seasons), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetTVShowDetails'. Liest die Eigenschaften eines TV-Serie aus.
     *
     * @access public
     * @param  integer $TvShowId TvShowId der zu lesenden TV-Serie.
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetTVShowDetails(integer $TvShowId)
    {
        if (!is_int($TvShowId))
        {
            trigger_error('TvShowId must be integer', E_USER_NOTICE);
            return false;
        }

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetTVShowDetails(array("tvshowid" => $TvShowId, "properties" => static::$TvShowItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return json_decode(json_encode($ret->tvshowdetails), true);
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_GetTVShows'. Liest die Eigenschaften aller TV-Serien.
     *
     * @access public
     * @return array | boolean Array mit den Daten oder false bei Fehlern.
     */
    public function GetTVShows()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->GetTVShows(array("properties" => static::$TvShowItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->limits->total > 0)
            return json_decode(json_encode($ret->tvshows), true);
        return array();
    }

    /**
     * IPS-Instanz-Funktion 'KODIVIDEOLIB_Scan'. Startet das Scannen der Quellen für neue Einträge in der Datenbank.
     *
     * @access public
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function Scan()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->Scan();
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === "OK";
    }

}

/** @} */
?>