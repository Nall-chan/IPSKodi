<?

require_once(__DIR__ . "/../KodiClass.php");  // diverse Klassen

class KodiDevicePlayer extends KodiBase
{

    const Audio = 0;
    const Video = 1;
    const Pictures = 2;

    static $Namespace = array('Player', ' Playlist');
    static $Properties = array(
        "type",
        "partymode",
        "speed",
        "time",
        "percentage",
        "totaltime",
        "playlistid",
        "position",
        "repeat",
        "shuffled",
        "canseek",
        "canchangespeed",
        "canmove",
        "canzoom",
        "canrotate",
        "canshuffle",
        "canrepeat",
        "currentaudiostream",
        "audiostreams",
        "subtitleenabled",
        "currentsubtitle",
        "subtitles",
        "live"
    );
    static $PartialProperties = array(
        "type",
        "partymode",
        "speed",
        "time",
        "percentage",
        "repeat",
        "shuffled",
        "currentaudiostream",
        "subtitleenabled",
        "currentsubtitle"
    );
    static $ItemList = array(
        "title",
        "artist",
        "albumartist",
        "genre",
        "year",
        "rating",
        "album",
        "track",
        "duration",
        "comment",
        "lyrics",
        "musicbrainztrackid",
        "musicbrainzartistid",
        "musicbrainzalbumid",
        "musicbrainzalbumartistid",
        "playcount",
        "fanart",
        "director",
        "trailer",
        "tagline",
        "plot",
        "plotoutline",
        "originaltitle",
        "lastplayed",
        "writer",
        "studio",
        "mpaa",
        "cast",
        "country",
        "imdbnumber",
        "premiered",
        "productioncode",
        "runtime",
        "set",
        "showlink",
        "streamdetails",
        "top250",
        "votes",
        "firstaired",
        "season",
        "episode",
        "showtitle",
        "thumbnail",
        "file",
        "resume",
        "artistid",
        "albumid",
        "tvshowid",
        "setid",
        "watchedepisodes",
        "disc",
        "tag",
        "art",
        "genreid",
        "displayartist",
        "albumartistid",
        "description",
        "theme",
        "mood",
        "style",
        "albumlabel",
        "sorttitle",
        "episodeguide",
        "uniqueid",
        "dateadded",
        "channel",
        "channeltype",
        "hidden",
        "locked",
        "channelnumber",
        "starttime",
        "endtime");
    private $PlayerId = null;
    private $isActive = null;
    static $Playertype = array(
        "song" => 0,
        "audio" => 0,
        "video" => 1,
        "episode" => 1,
        "movie" => 1,
        "pictures" => 2
    );

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('PlayerID', 0);
        $this->RegisterPropertyInteger('CoverSize', 300);
        $this->RegisterPropertyString('CoverTyp', 'thumb');
    }

    public function ApplyChanges()
    {
        $this->Init();
        $this->RegisterVariableBoolean("_isactive", "isplayeractive", "", -5);
        IPS_SetHidden($this->GetIDForIdent('_isactive'), true);
        
        $this->RegisterProfileIntegerEx("Repeat.Kodi", "", "", "", Array(
            //Array(0, "Prev", "", -1),
            Array(0, "Aus", "", -1),
            Array(1, "Titel", "", -1),
            Array(2, "Playlist", "", -1)
        ));
        $this->RegisterProfileIntegerEx("Speed.Kodi", "Intensity", "", "", Array(
            //Array(0, "Prev", "", -1),
            Array(-32, "32 <<", "", -1),
            Array(-16, "16 <<", "", -1),
            Array(-8, "8 <<", "", -1),
            Array(-4, "4 <<", "", -1),
            Array(-2, "2 <<", "", -1),
            Array(-1, "1 <<", "", -1),
            Array(0, "Pause", "", 0x0000FF),
            Array(1, "Play", "", 0x00FF00),
            Array(2, "2 >>", "", -1),
            Array(4, "4 >>", "", -1),
            Array(8, "8 >>", "", -1),
            Array(16, "16 >>", "", -1),
            Array(32, "32 >>", "", -1)
        ));

        $this->RegisterProfileInteger("Intensity.Kodi", "Intensity", "", " %", 0, 100, 1);

        switch ($this->PlayerId)
        {
            case self::Audio:
                $this->UnregisterVariable("showtitle");
                $this->UnregisterVariable("season");
                $this->UnregisterVariable("episode");
                $this->UnregisterVariable("plot");
                $this->UnregisterVariable("audioindex");
                $this->UnregisterVariable("audiolanguage");
                $this->UnregisterVariable("audiochannels");
                $this->UnregisterVariable("audiocodec");
                $this->UnregisterVariable("audiobitrate");
                $this->UnregisterVariable("audiostreams");
                $this->UnregisterVariable("subtitleenabled");
                $this->UnregisterVariable("subtitle");
                $this->UnregisterVariable("subtitles");

                $this->UnregisterProfile("AudioTracks." . $this->InstanceID . ".Kodi");

                $this->RegisterProfileIntegerEx("Status." . $this->InstanceID . ".Kodi", "Information", "", "", Array(
                    Array(0, "Prev", "", -1),
                    Array(1, "Stop", "", -1),
                    Array(2, "Play", "", -1),
                    Array(3, "Pause", "", -1),
                    Array(4, "Next", "", -1)
                ));

                $this->RegisterVariableInteger("position", "Playlist Position", "", 9);
                $this->RegisterVariableInteger("repeat", "Wiederholen", "Repeat.Kodi", 11);
                $this->RegisterVariableBoolean("shuffled", "Zufall", "~Switch", 12);
                $this->RegisterVariableBoolean("partymode", "Partymodus", "~Switch", 13);
                $this->EnableAction("partymode");
                $this->RegisterVariableString("album", "Album", "", 15);
                $this->RegisterVariableInteger("track", "Track", "", 16);
                $this->RegisterVariableInteger("disc", "Disc", "", 17);
                $this->RegisterVariableString("artist", "Artist", "", 20);
                $this->RegisterVariableString("lyrics", "Lyrics", "", 30);

                break;
            case self::Video:
                $this->UnregisterVariable("position");
                $this->UnregisterVariable("repeat");
                $this->UnregisterVariable("shuffled");
                $this->UnregisterVariable("partymode");
                $this->UnregisterVariable("album");
                $this->UnregisterVariable("track");
                $this->UnregisterVariable("disc");
                $this->UnregisterVariable("artist");
                $this->UnregisterVariable("lyrics");

                $this->RegisterProfileIntegerEx("Status." . $this->InstanceID . ".Kodi", "Information", "", "", Array(
                    Array(1, "Stop", "", -1),
                    Array(2, "Play", "", -1),
                    Array(3, "Pause", "", -1)
                ));
                $this->RegisterProfileInteger("AudioTracks." . $this->InstanceID . ".Kodi", "", "", "", 1, 1, 1);

                $this->RegisterVariableString("showtitle", "Serie", "", 13);
                $this->RegisterVariableInteger("season", "Staffel", "", 15);
                $this->RegisterVariableInteger("episode", "Episode", "", 16);

                $this->RegisterVariableString("plot", "Handlung", "~TextBox", 19);
                $this->RegisterVariableInteger("audioindex", "Aktueller Audiotrack", "AudioTracks." . $this->InstanceID . ".Kodi", 30);
                $this->RegisterVariableString("audiolanguage", "Sprache", "", 31);
                $this->RegisterVariableInteger("audiochannels", "Audiokanäle", "", 32);
                $this->RegisterVariableString("audiocodec", "Audio Codec", "", 23);
                $this->RegisterVariableInteger("audiobitrate", "Audio Bitrate", "", 34);
                $this->RegisterVariableInteger("audiostreams", "Anzahl Audiotracks", "", 35);
                $this->RegisterVariableBoolean("subtitleenabled", "Untertitel aktiv", "~Switch", 40);
                $this->RegisterVariableInteger("subtitle", "Aktiver Untertitel", "Subtitels." . $this->InstanceID . ".Kodi", 41);
                $this->RegisterVariableInteger("subtitles", "Anzahl Untertitel", "", 42);
                break;
            case self::Pictures:
                $this->RegisterProfileIntegerEx("Status." . $this->InstanceID . ".Kodi", "Information", "", "", Array(
                    Array(0, "Prev", "", -1),
                    Array(1, "Stop", "", -1),
                    Array(2, "Play", "", -1),
                    Array(3, "Pause", "", -1),
                    Array(4, "Next", "", -1)
                ));
                break;
        }
                $this->RegisterVariableString("label", "Titel", "", 14);
        $this->RegisterVariableString("genre", "Genre", "", 21);
        $this->RegisterVariableInteger("Status", "Status", "Status." . $this->InstanceID . ".Kodi", 3);
        $this->EnableAction("Status");
        $this->RegisterVariableInteger("speed", "Geschwindigkeit", "Speed.Kodi", 10);
        $this->RegisterVariableInteger("year", "Jahr", "", 19);
        $this->RegisterVariableString("type", "Typ", "", 20);
        $this->RegisterVariableString("duration", "Dauer", "", 24);
        $this->RegisterVariableString("time", "Spielzeit", "", 25);
        $this->RegisterVariableInteger("percentage", "Position", "Intensity.Kodi", 26);


        $this->getActivePlayer();

        parent::ApplyChanges();

        if ($this->isActive)
            $this->GetItemInternal();

        $this->RegisterTimer('PlayerStatus', 0, 'KODIPLAYER_RequestState($_IPS[\'TARGET\'],"PARTIAL");');
    }

################## PRIVATE     

    private function Init()
    {
        if (is_null($this->PlayerId))
            $this->PlayerId = $this->ReadPropertyInteger('PlayerID');
        if (is_null($this->isActive))
            $this->isActive = GetValueBoolean($this->GetIDForIdent('_isactive'));
    }

    private function getActivePlayer()
    {
        $this->Init();
        $KodiData = new Kodi_RPC_Data(static::$Namespace[0], 'GetActivePlayers');
        $ret = $this->Send($KodiData);
        if (is_null($ret) or ( count($ret) == 0))
            $this->isActive = false;
        else
            $this->isActive = ((int) $ret[0]->playerid == $this->PlayerId);

        $this->SetValueBoolean('_isactive', $this->isActive);
        return (bool) $this->isActive;
    }

    private function setActivePlayer(boolean $isActive)
    {
        $this->isActive = $isActive;
        $this->SetValueBoolean('_isactive', $isActive);
    }

    protected function RequestProperties(array $Params)
    {
        $this->Init();
        $Param = array_merge($Params, array("playerid" => $this->PlayerId));
        //parent::RequestProperties($Params);
        if (!$this->isActive)
            return false;
        $KodiData = new Kodi_RPC_Data(static::$Namespace[0], 'GetProperties', $Param);
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        $this->Decode('GetProperties', $ret);
    }

    protected function Decode($Method, $KodiPayload)
    {
        $this->Init();
        if (property_exists($KodiPayload, 'player'))
        {
            if ($KodiPayload->player->playerid <> $this->PlayerId)
                return false;
        }
        else
        {
            if (property_exists($KodiPayload, 'type'))
            {
                if (self::$Playertype[(string) $KodiPayload->type] <> $this->PlayerId)
                    return false;
            }
            else
            {
                if (property_exists($KodiPayload, 'item'))
                {
                    if (self::$Playertype[(string) $KodiPayload->item->type] <> $this->PlayerId)
                        return false;
                }
            }
        }
        switch ($Method)
        {
            case 'GetProperties':
            case 'OnPropertyChanged':
                foreach ($KodiPayload as $param => $value)
                {
                    /*
                      {"audiostreams":[{"bitrate":416825,"channels":6,"codec":"ac3","index":0,"language":"ger","name":"AC3 5.1"}],

                      "canchangespeed":true,"canmove":false,"canrepeat":true,"canrotate":false,
                      "canseek":true,"canshuffle":true,"canzoom":false,
                      "currentaudiostream":{"bitrate":416825,"channels":6,"codec":"ac3","index":0,"language":"ger","name":"AC3 5.1"},
                      "currentsubtitle":{},
                      "live":false,"partymode":false,
                      "percentage":1.855262279510498,
                      "playlistid":1,"position":-1,"repeat":"off",
                      "shuffled":false,"speed":1,
                      "subtitleenabled":true,"subtitles":[],"time":{"hours":0,"milliseconds":446,"minutes":2,"seconds":49},"totaltime":{"hours":2,"milliseconds":264,"minutes":32,"seconds":13},"type":"video"}
                     */
                    switch ($param)
                    {
                        // Object
                        case "currentsubtitle":
                            if ($this->PlayerId <> self::Video)
                                break;
                            if (is_object($value))
                            {
                                if (property_exists($value, 'index'))
                                {
                                    $this->SetValueInteger('subtitle', (int) $value->index);
//                                    $this->SetValueBoolean('subtitleenabled', true);
                                }
                                else
                                {
                                    $this->SetValueInteger('subtitle', -1);
                                    //                                  $this->SetValueBoolean('subtitleenabled', false);
                                }
                            }
                            else
                            {
                                $this->SetValueInteger('subtitle', -1);
                            }
                            break;
                        case "currentaudiostream":
                            if ($this->PlayerId <> self::Video)
                                break;
                            if (is_object($value))
                            {
                                if (property_exists($value, 'bitrate'))
                                    $this->SetValueInteger('audiobitrate', (int) $value->bitrate);
                                else
                                    $this->SetValueInteger('audiobitrate', 0);

                                if (property_exists($value, 'channels'))
                                    $this->SetValueInteger('audiochannels', (int) $value->channels);
                                else
                                    $this->SetValueInteger('audiochannels', 0);

                                if (property_exists($value, 'index'))
                                    $this->SetValueInteger('audioindex', (int) $value->index);
                                else
                                    $this->SetValueInteger('audioindex', 0);

                                if (property_exists($value, 'language'))
                                    $this->SetValueString('audiolanguage', (string) $value->language);
                                else
                                    $this->SetValueString('audiolanguage', "");

                                if (property_exists($value, 'name'))
                                    $this->SetValueString('audiocodec', (string) $value->name);
                                else
                                    $this->SetValueString('audiocodec', "");
                            } else
                            {
                                $this->SetValueInteger('audiobitrate', 0);
                                $this->SetValueInteger('audiochannels', 0);
                                $this->SetValueInteger('audioindex', 0);
                                $this->SetValueString('audiolanguage', "");
                                $this->SetValueString('audiocodec', "");
                            }
                            break;
                        //string
                        case "type":
//                            $this->SetValueString($param, (string) $value);
                            break;
                        //time
                        case "totaltime":
                            $this->SetValueString('duration', $this->ConvertTime($value));
                            break;
                        case "time":
                            $this->SetValueString($param, $this->ConvertTime($value));
                            break;
                        // Anzahl
                        case "audiostreams":
                            if ($this->PlayerId <> self::Video)
                                break;
                            $this->SetValueInteger($param, count($value));
                            //Profil anpassen
                            break;
                        case "subtitles":
                            if ($this->PlayerId <> self::Video)
                                break;
                            $this->SetValueInteger($param, count($value));
                            //Profil anpassen
                            break;
                        case "repeat": //off
                            if ($this->PlayerId == self::Video)
                                break;
                            $this->SetValueInteger($param, array_search((string) $value, array("off", "one", "all")));
                            break;
                        //boolean
                        case "shuffled":
                        case "partymode":
                            if ($this->PlayerId == self::Video)
                                break;
                            $this->SetValueBoolean($param, (bool) $value);
                            break;

                        case "subtitleenabled":
                            if ($this->PlayerId <> self::Video)
                                break;
                            $this->SetValueBoolean($param, (bool) $value);
                            break;
                        //integer
                        case "speed":
                            $this->SetValueInteger('Status', 2);
                        case "percentage":
                            $this->SetValueInteger($param, (int) $value);
                            break;
                        case "position":
                            if ($this->PlayerId == self::Video)
                                break;

                            $this->SetValueInteger($param, (int) $value + 1);
                            break;

                        /*    {"canrotate":false,"canzoom":false,
                          "currentsubtitle":null,
                          "live":false,"playlistid":1,
                          "subtitles":[],
                         */

                        //Action en/disable
                        case "canseek":
                            if ((bool) $value)
                                $this->EnableAction('percentage');
                            else
                                $this->DisableAction('percentage');
                            break;
                        case "canshuffle":
                            if ($this->PlayerId == self::Video)
                                break;
                            if ((bool) $value)
                                $this->EnableAction('shuffled');
                            else
                                $this->DisableAction('shuffled');
                            break;
                        case "canrepeat":
                            if ($this->PlayerId == self::Video)
                                break;
                            if ((bool) $value)
                                $this->EnableAction('repeat');
                            else
                                $this->DisableAction('repeat');
                            break;
                        case "canchangespeed":
                            if ((bool) $value)
                                $this->EnableAction('speed');
                            else
                                $this->DisableAction('speed');
                            break;
                        default:
                            IPS_LogMessage($param, print_r($value, true));
                            break;
                    }
                }
                break;
            case 'OnStop':
                $this->SetTimerInterval('PlayerStatus', 0);
                $this->SetValueInteger('Status', 1);
                $this->SetValueString('duration', '');
                $this->SetValueString('time', '');
                $this->SetValueInteger('percentage', 0);
                $this->setActivePlayer(false);
                IPS_RunScriptText('<? KODIPLAYER_RequestState(' . $this->InstanceID . ',"ALL");');
                IPS_RunScriptText('<? KODIPLAYER_GetItemInternal(' . $this->InstanceID . ');');

                break;
            case 'OnPlay':
                $this->setActivePlayer(true);
                $this->SetValueInteger('Status', 2);
                IPS_RunScriptText('<? KODIPLAYER_RequestState(' . $this->InstanceID . ',"ALL");');
                IPS_RunScriptText('<? KODIPLAYER_GetItemInternal(' . $this->InstanceID . ');');
                $this->SetTimerInterval('PlayerStatus', 2);
                break;
            case 'OnPause':
                $this->SetTimerInterval('PlayerStatus', 0);
                $this->SetValueInteger('Status', 3);
                IPS_RunScriptText('<? KODIPLAYER_RequestState(' . $this->InstanceID . ',"ALL");');
                break;
            case 'OnSeek':
                $this->SetValueString('time', $this->ConvertTime($KodiPayload->player->time));
                break;
            case 'OnSpeedChanged':
                IPS_RunScriptText('<? KODIPLAYER_RequestState(' . $this->InstanceID . ',"speed");');
                break;
            default:
                IPS_LogMessage($Method, print_r($KodiPayload, true));
                break;
        }
    }

    private function SetCover($file)
    {
//        $Ext = pathinfo($file, PATHINFO_EXTENSION);
        $CoverID = @IPS_GetObjectIDByIdent('CoverIMG', $this->InstanceID);
        $filename = "media" . DIRECTORY_SEPARATOR . "Cover_" . $this->InstanceID . ".png";
        $Size = $this->ReadPropertyString("CoverSize");
        if ($CoverID === false)
        {
            $CoverID = IPS_CreateMedia(1);
            IPS_SetParent($CoverID, $this->InstanceID);
            IPS_SetIdent($CoverID, 'CoverIMG');
            IPS_SetName($CoverID, 'Cover');
            IPS_SetPosition($CoverID, 27);
            IPS_SetMediaCached($CoverID, true);
            IPS_SetMediaFile($CoverID, $filename, False);
        }

        if ($file == "")
            $CoverRAW = FALSE;
        else
        {
            $ParentID = $this->GetParent();
            if ($ParentID !== false)
                $CoverRAW = KODIRPC_GetImage($ParentID, $file);
        }

        if (!($CoverRAW === false))
        {
            $image = imagecreatefromstring($CoverRAW);
            if (!($image === false))
            {
                $width = imagesx($image);
                $height = imagesy($image);
                if ($height > $Size)
                {
                    $factor = $height / $Size;
                    $image = imagescale($image, $width / $factor, $height / $factor);
                }
                ob_start();
                imagepng($image);
                $CoverRAW = ob_get_contents(); // read from buffer                
                ob_end_clean(); // delete buffer                
            }
        }

        if ($CoverRAW === false)
            $CoverRAW = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "nocover.png");

        IPS_SetMediaContent($CoverID, base64_encode($CoverRAW));
        return;
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "Status":
                switch ($Value)
                {
                    case 0: //Prev
                        $result = $this->Previous();
                        break;
                    case 1: //Stop
                        $result = $this->Stop();
                        break;
                    case 2: //Play
                        $result = $this->Play();
                        break;
                    case 3: //Pause
                        $result = $this->Pause();
                        break;
                    case 4: //Next
                        $result = $this->Next();
                        break;
                }
                return $result;
            case "shuffled":
                return $this->SetShuffle($Value);
            case "repeat":
                return $this->SetRepeat($Value);
            case "speed":
                return $this->SetSpeed($Value);
            case "partymode":
                return $this->SetPartymode($Value);
            case "percentage":
                return $this->SetPosition($Value);
//            default:
//                return trigger_error('Invalid Ident.', E_USER_NOTICE);
        }
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

    public function RawSend(string $Namespace, string $Method, $Params)
    {
        return parent::RawSend($Namespace, $Method, $Params);
    }

    public function GetItemInternal()
    {
        $ret = $this->GetItem();
        if (is_null($ret))
            return null;
        switch ($this->PlayerId)
        {
            case self::Audio:
                $this->SetValueString('label', $ret->label);
                $this->SetValueString('type', $ret->type);

                if (property_exists($ret, 'displayartist'))
                    $this->SetValueString('artist', $ret->displayartist);
                else
                {
                    if (property_exists($ret, 'albumartist'))
                    {
                        if (is_array($ret->artist))
                            $this->SetValueString('artist', implode(', ', $ret->albumartist));
                        else
                            $this->SetValueString('artist', $ret->albumartist);
                    }
                    else
                    {
                        if (property_exists($ret, 'artist'))
                        {
                            if (is_array($ret->artist))
                                $this->SetValueString('artist', implode(', ', $ret->artist));
                            else
                                $this->SetValueString('artist', $ret->artist);
                        }
                        else
                            $this->SetValueString('artist', "");
                    }
                }

                if (property_exists($ret, 'genre'))
                {
                    if (is_array($ret->genre))
                        $this->SetValueString('genre', implode(', ', $ret->genre));
                    else
                        $this->SetValueString('genre', $ret->genre);
                }
                else
                    $this->SetValueString('genre', "");

                if (property_exists($ret, 'album'))
                    $this->SetValueString('album', $ret->album);
                else
                    $this->SetValueString('album', "");

                if (property_exists($ret, 'year'))
                    $this->SetValueInteger('year', $ret->year);
                else
                    $this->SetValueInteger('year', 0);

                if (property_exists($ret, 'track'))
                    $this->SetValueInteger('track', $ret->track);
                else
                    $this->SetValueInteger('track', 0);

                if (property_exists($ret, 'disc'))
                    $this->SetValueInteger('disc', $ret->disc);
                else
                    $this->SetValueInteger('disc', 0);

                if (property_exists($ret, 'duration'))
                    $this->SetValueString('duration', $this->ConvertTime($ret->duration));
                else
                    $this->SetValueString('duration', "");

                if (property_exists($ret, 'lyrics'))
                    $this->SetValueString('lyrics', $ret->lyrics);
                else
                    $this->SetValueString('lyrics', "");

                switch ($this->ReadPropertyString('CoverTyp'))
                {
                    case"artist":
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'artist.fanart'))
                                if ($ret->art->{'artist.fanart'} <> "")
                                {
                                    $this->SetCover($ret->art->{'artist.fanart'});
                                    break;
                                }
                        }
                        if (property_exists($ret, 'fanart'))
                            if ($ret->fanart <> "")
                            {
                                $this->SetCover($ret->fanart);
                                break;
                            }
                    default:
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'thumb'))
                                if ($ret->art->thumb <> "")
                                {
                                    $this->SetCover($ret->art->thumb);
                                    break;
                                }
                        }
                        if (property_exists($ret, 'thumbnail'))
                        {
                            if ($ret->thumbnail <> "")
                            {
                                $this->SetCover($ret->thumbnail);
                                break;
                            }
                        }
                        break;
                }

                break;
            case self::Video:
                if (property_exists($ret, 'showtitle'))
                    $this->SetValueString('showtitle', $ret->showtitle);
                else
                    $this->SetValueString('showtitle', "");
                
                $this->SetValueString('label', $ret->label);

                if (property_exists($ret, 'season'))
                    $this->SetValueInteger('season', $ret->season);
                else
                    $this->SetValueInteger('season', -1);

                if (property_exists($ret, 'episode'))
                    $this->SetValueInteger('episode', $ret->episode);
                else
                    $this->SetValueInteger('episode', -1);

                if (property_exists($ret, 'genre'))
                {
                    if (is_array($ret->genre))
                        $this->SetValueString('genre', implode(', ', $ret->genre));
                    else
                        $this->SetValueString('genre', $ret->genre);
                }
                else
                    $this->SetValueString('genre', "");

                if (property_exists($ret, 'runtime'))
                    $this->SetValueString('duration', $this->ConvertTime($ret->runtime));
                else
                    $this->SetValueString('duration', "");

                if (property_exists($ret, 'year'))
                    $this->SetValueInteger('year', $ret->year);
                else
                    $this->SetValueInteger('year', 0);

                if (property_exists($ret, 'plot'))
                    $this->SetValueString('plot', $ret->plot);
                else
                    $this->SetValueString('plot', "");

                switch ($this->ReadPropertyString('CoverTyp'))
                {
                    case"poster":
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'tvshow.poster'))
                            {
                                if ($ret->art->{'tvshow.poster'} <> "")
                                {
                                    $this->SetCover($ret->art->{'tvshow.poster'});
                                    break;
                                }
                            }
                            if (property_exists($ret->art, 'poster'))
                            {
                                if ($ret->art->{'poster'} <> "")
                                {
                                    $this->SetCover($ret->art->{'poster'});
                                    break;
                                }
                            }
                        }
                        if (property_exists($ret, 'poster'))
                        {
                            if ($ret->poster <> "")
                            {
                                $this->SetCover($ret->poster);
                                break;
                            }
                        }
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'tvshow.banner'))
                            {
                                if ($ret->art->{'tvshow.banner'} <> "")
                                {
                                    $this->SetCover($ret->art->{'tvshow.banner'});
                                    break;
                                }
                            }
                        }
                        if (property_exists($ret, 'banner'))
                        {
                            if ($ret->banner <> "")
                            {
                                $this->SetCover($ret->banner);
                                break;
                            }
                        }
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'thumb'))
                                if ($ret->art->thumb <> "")
                                {
                                    $this->SetCover($ret->art->thumb);
                                    break;
                                }
                        }
                        if (property_exists($ret, 'thumbnail'))
                        {
                            if ($ret->thumbnail <> "")
                            {
                                $this->SetCover($ret->thumbnail);
                                break;
                            }
                        }
                        $this->SetCover("");

                        break;
                    case"banner":
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'tvshow.banner'))
                            {
                                if ($ret->art->{'tvshow.banner'} <> "")
                                {
                                    $this->SetCover($ret->art->{'tvshow.banner'});
                                    break;
                                }
                            }
                        }
                        if (property_exists($ret, 'banner'))
                        {
                            if ($ret->banner <> "")
                            {
                                $this->SetCover($ret->banner);
                                break;
                            }
                        }
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'tvshow.poster'))
                            {
                                if ($ret->art->{'tvshow.poster'} <> "")
                                {
                                    $this->SetCover($ret->art->{'tvshow.poster'});
                                    break;
                                }
                            }
                            if (property_exists($ret->art, 'poster'))
                            {
                                if ($ret->art->{'poster'} <> "")
                                {
                                    $this->SetCover($ret->art->{'poster'});
                                    break;
                                }
                            }
                        }
                        if (property_exists($ret, 'poster'))
                        {
                            if ($ret->poster <> "")
                            {
                                $this->SetCover($ret->poster);
                                break;
                            }
                        }
                    default:
                        if (property_exists($ret, 'art'))
                        {
                            if (property_exists($ret->art, 'thumb'))
                                if ($ret->art->thumb <> "")
                                {
                                    $this->SetCover($ret->art->thumb);
                                    break;
                                }
                        }
                        if (property_exists($ret, 'thumbnail'))
                        {
                            if ($ret->thumbnail <> "")
                            {
                                $this->SetCover($ret->thumbnail);
                                break;
                            }
                        }
                        $this->SetCover("");

                        break;
                }
                break;

            /*      EPISODE

              "streamdetails":
              {
              "audio":[{"channels":6,"codec":"ac3","language":""}],
              "subtitle":[{"language":""}],
              "video":[{"aspect":1.7777800559997559,"codec":"h264","duration":3421,"height":720,"stereomode":"","width":1280}]
              }
             */
            /*      MOVIE
              ["streamdetails"]=>
              object(stdClass)#13 (3) {
              ["audio"]=>
              array(1) {
              [0]=>
              object(stdClass)#14 (3) {
              ["channels"]=>
              int(2)
              ["codec"]=>
              string(3) "mp3"
              ["language"]=>
              string(0) ""
              }
              }
              ["subtitle"]=>
              array(0) {
              }
              ["video"]=>
              array(1) {
              [0]=>
              object(stdClass)#15 (6) {
              ["aspect"]=>
              float(1.8181799650192)
              ["codec"]=>
              string(4) "xvid"
              ["duration"]=>
              int(6462)
              ["height"]=>
              int(352)
              ["stereomode"]=>
              string(0) ""
              ["width"]=>
              int(640)
              }
              }
              }
             */
        }
    }

    public function GetItem()
    {
        $this->Init();
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'GetItem', array('playerid' => $this->PlayerId, 'properties' => self::$ItemList));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return null;
        return $ret->item;
    }

    public function Play()
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'PlayPause', array("playerid" => $this->PlayerId, "play" => true));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->speed === 1)
        {
            $this->SetValueInteger("Status", 2);
            return true;
        }
        else
        {
            trigger_error('Error on send play.', E_USER_NOTICE);
        }

        return false;
    }

    public function Pause()
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'PlayPause', array("playerid" => $this->PlayerId, "play" => false));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret->speed === 0)
        {
            $this->SetValueInteger("Status", 3);
            return true;
        }
        else
        {
            trigger_error('Error on send pause.', E_USER_NOTICE);
        }

        return false;
    }

    public function Stop()
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'Stop', array("playerid" => $this->PlayerId));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
        {
            $this->SetValueInteger("Status", 1);
            return true;
        }
        else
        {
            trigger_error('Error on send stop.', E_USER_NOTICE);
        }
        return false;
    }

    public function Next()
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'GoTo', array("playerid" => $this->PlayerId, "to" => "next"));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
            return true;
        trigger_error('Error on send next.', E_USER_NOTICE);
        return false;
    }

    public function Previous()
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'GoTo', array("playerid" => $this->PlayerId, "to" => "previous"));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
            return true;
        trigger_error('Error on send previous.', E_USER_NOTICE);
        return false;
    }

    public function GoToTrack(integer $Value)
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'GoTo', array("playerid" => $this->PlayerId, "to" => $Value + 1));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
            return true;
        trigger_error('Error on goto track.', E_USER_NOTICE);
        return false;
    }

    public function SetShuffle(boolean $Value)
    {
        $this->Init();
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'SetShuffle', array("playerid" => $this->PlayerId, "shuffle" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
        {
            $this->SetValueBoolean("shuffled", $Value);
            return true;
        }
        else
        {
            trigger_error('Error on set shuffle.', E_USER_NOTICE);
        }

        return false;
    }

    public function SetRepeat(integer $Value)
    {
        $this->Init();
        $repeat = array("off", "one", "all");
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'SetRepeat', array("playerid" => $this->PlayerId, "repeat" => $repeat[$Value]));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
        {
            $this->SetValueInteger("repeat", $Value);
            return true;
        }
        else
        {
            trigger_error('Error on set repeat.', E_USER_NOTICE);
        }

        return false;
    }

    public function SetPartymode(boolean $Value)
    {
        $this->Init();
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'SetPartymode', array("playerid" => $this->PlayerId, "partymode" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === "OK")
        {
            $this->SetValueBoolean("partymode", $Value);
            return true;
        }
        else
        {
            trigger_error('Error on set partymode.', E_USER_NOTICE);
        }

        return false;
    }

    public function SetSpeed(integer $Value)
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }

        if (!in_array($Value, array(-32, -16, -8, -4, -2, 0, 1, 2, 4, 8, 16, 32)))
        {
            trigger_error('Invalid Value for speed.', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'SetSpeed', array("playerid" => $this->PlayerId, "speed" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ((int) $ret->speed == $Value)
        {
            $this->SetValueInteger("speed", $Value);
            return true;
        }
        else
        {
            trigger_error('Error on set speed.', E_USER_NOTICE);
        }
        return false;
    }

    public function SetPosition(integer $Value)
    {
        $this->Init();
        if (!$this->isActive)
        {
            trigger_error('Player not active', E_USER_NOTICE);
            return false;
        }
        /*        if ($this->PlayerId <> self::Audio)
          {
          trigger_error('Not supported', E_USER_NOTICE);
          return false;
          } */
    }

//    public function Volume(integer $Value)
//    {
//        if (!is_int($Value))
//        {
//            trigger_error('Value must be integer', E_USER_NOTICE);
//            return false;
//        }
////        $KodiData = new Kodi_RPC_Data(self::$Namespace[0, 'SetVolume', array("volume" => $Value));
//        $KodiData = new Kodi_RPC_Data(self::$Namespace[0);
//        $KodiData->SetVolume(array("volume" => $Value));
//        $ret = $this->Send($KodiData);
//        if (is_null($ret))
//            return false;
//        $this->SetValueInteger("volume", $ret);
//        return $ret['volume'] === $Value;
//    }
//
//    public function Quit()
//    {
//        $KodiData = new Kodi_RPC_Data(self::$Namespace[0], 'Quit');
//        $ret = $this->Send($KodiData);
//        if (is_null($ret))
//            return false;
//        return true;
//    }

    public function RequestState(string $Ident)
    {
        return parent::RequestState($Ident);
    }

    /*
      public function Pause()
      {

      }

      public function Stop()
      {

      }

     */
################## Datapoints

    public function ReceiveData($JSONString)
    {
        return parent::ReceiveData($JSONString);
    }

    /*
      protected function Send(Kodi_RPC_Data $KodiData)
      {
      return parent::Send($KodiData);
      }

      protected function SendDataToParent($Data)
      {
      return parent::SendDataToParent($Data);
      }
     */
}

?>