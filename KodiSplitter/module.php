<?

require_once(__DIR__ . "/../KodiClass.php");  // diverse Klassen

/*
 * @addtogroup kodi
 * @{
 *
 * @package       Kodi
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */

/**
 * KodiSplitter Klasse für die Kommunikation mit der Kodi-RPC-Api.
 * Enthält den Namespace JSONRPC.
 * Erweitert IPSModule.
 * 
 * @package       Kodi
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2016 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0 
 * @example <b>Ohne</b>
 */
class KodiSplitter extends IPSModule
{

    /**
     * RPC-Namespace
     * 
     * @access private
     *  @var string
     * @value 'JSONRPC'
     */
    static $Namespace = "JSONRPC";

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
        $this->RegisterPropertyString("Host", "");
        $this->RegisterPropertyBoolean("Open", false);
        $this->RegisterPropertyInteger("Port", 9090);
        $this->RegisterPropertyInteger("Webport", 80);
        $this->RegisterPropertyBoolean("BasisAuth", false);
        $this->RegisterPropertyString("Username", "");
        $this->RegisterPropertyString("Password", "");
        $this->RegisterPropertyBoolean("Watchdog", false);
        $this->RegisterPropertyInteger("Interval", 5);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message)
        {
            case IPS_KERNELMESSAGE:
                if ($Data[0] == KR_READY)
                {
                    try
                    {
                        $this->KernelReady();
                    }
                    catch (Exception $exc)
                    {
                        return;
                    }
                }
                break;
            case DM_CONNECT:
            case DM_DISCONNECT:
                $this->ForceRefresh();
                break;
            case IM_CHANGESTATUS:
                if (($SenderID == @IPS_GetInstance($this->InstanceID)['ConnectionID']) and ( $Data[0] == IS_ACTIVE))
                    try
                    {
                        $this->ForceRefresh();
                    }
                    catch (Exception $exc)
                    {
                        return;
                    }
                break;
        }
    }

    /**
     * Wird ausgeführt wenn der Kernel hochgefahren wurde.
     */
    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Parent ändert.
     */
    protected function ForceRefresh()
    {
        $this->ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     * 
     * @access public
     */
    public function ApplyChanges()
    {
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage($this->InstanceID, DM_CONNECT);
        $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);
        // Wenn Kernel nicht bereit, dann warten... KR_READY kommt ja gleich

        if (IPS_GetKernelRunlevel() <> KR_READY)
            return;

        parent::ApplyChanges();
        // Kurzinfo setzen
        $this->SetSummary($this->ReadPropertyString('Host'));
        // Buffer leeren
        $this->SetBuffer('ReplyJSONData', serialize(array())); // 
        // Config prüfen
        $Open = $this->ReadPropertyBoolean('Open');
        $NewState = IS_ACTIVE;
        if (!$Open)
            $NewState = IS_INACTIVE;
        else
        {
            if ($this->ReadPropertyString('Host') == '')
            {
                $NewState = IS_EBASE + 2;
                $Open = false;
                trigger_error('Host is empty', E_USER_NOTICE);
            }
            if ($this->ReadPropertyInteger('Port') == 0)
            {
                $NewState = IS_EBASE + 2;
                $Open = false;
                trigger_error('Port is empty', E_USER_NOTICE);
            }
            if ($this->ReadPropertyInteger('Webport') == 0)
            {
                $NewState = IS_EBASE + 2;
                $Open = false;
                trigger_error('Webport is empty', E_USER_NOTICE);
            }
        }
        $ParentID = $this->GetParent();

        // Zwangskonfiguration des ClientSocket
        if ($ParentID > 0)
        {
            // Dup Applychange vermeiden
            $this->UnregisterMessage($ParentID, IM_CHANGESTATUS);

            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));

            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));

            // Keine Verbindung erzwingen wenn Host offline ist
            if ($Open)
            {
                $Open = @Sys_Ping($this->ReadPropertyString('Host'), 500);
                if (!$Open)
                    $NewState = IS_INACTIVE;
            }
            $this->OpenIOParent($ParentID, $Open);
        }
        else
        {
            if ($Open)
            {
                $NewState = IS_INACTIVE;
                $Open = false;
            }
        }
        // Eigene Profile
        $this->UnregisterVariable("BufferIN");
        $this->UnregisterVariable("ReplyJSONData");

        $this->RegisterTimer('KeepAlive', 0, 'KODIRPC_KeepAlive($_IPS[\'TARGET\']);');
        if ($this->ReadPropertyBoolean('Watchdog'))
            $this->RegisterTimer('Watchdog', 0, 'KODIRPC_Watchdog($_IPS[\'TARGET\']);');
        else
            $this->UnregisterTimer('Watchdog');

        // Wenn wir verbunden sind,  mit Kodi, dann anmelden für Events

        if ($Open)
        {
            if ($this->HasActiveParent($ParentID))
            {
                $KodiData = new Kodi_RPC_Data('JSONRPC', 'Ping');
                $ret = @$this->Send($KodiData);
                if ($ret == "pong")
                {
                    if (!$this->CheckWebserver())
                    {
                        $NewState = IS_EBASE + 4;
                        trigger_error('Could not connect to webserver.', E_USER_NOTICE);
                    }
                }
                else
                {
                    $NewState = IS_EBASE + 3;
                    trigger_error('No answer', E_USER_NOTICE);
                }
            }
            else
            {
                $NewState = IS_EBASE + 3;
                trigger_error('Could not connect RPC-Server.', E_USER_NOTICE);
            }
        }

        $this->GetParentData();

        $this->SetStatus($NewState);

        switch ($NewState)
        {
            case IS_ACTIVE:
                $this->SetWatchdogTimer(false);
                $this->SetTimerInterval("KeepAlive", 180 * 1000);
                $InstanceIDs = IPS_GetInstanceList();
                foreach ($InstanceIDs as $IID)
                {
                    if (IPS_GetInstance($IID)['ConnectionID'] == $this->InstanceID)
                        @IPS_ApplyChanges($IID);
                }
                $this->SendPowerEvent(true);
                break;
            case IS_INACTIVE:
                $this->SetWatchdogTimer(true);
                $this->SendPowerEvent(false);
                break;
            case IS_EBASE + 2: //misconfig
                $this->SetWatchdogTimer(false);
                $this->SetTimerInterval("KeepAlive", 0);
                break;
            case IS_EBASE + 3: //ERROR
            case IS_EBASE + 4: //ERROR WebServer
                $this->OpenIOParent($ParentID, false);
                $this->SetWatchdogTimer(true);
                $this->SetTimerInterval("KeepAlive", 0);
                $this->SendPowerEvent(false);
                break;
        }
    }

################## PRIVATE     

    /**
     * Prüft die Verbindung zum WebServer.
     * 
     * @return boolean True bei Erfolg, sonst false.
     */
    private function CheckWebserver()
    {
        $KodiData = new Kodi_RPC_Data('JSONRPC', 'Ping');
        $JSON = $KodiData->ToRawRPCJSONString();
        $Result = $this->DoWebserverRequest("/jsonrpc?request=" . rawurlencode($JSON));
        if ($Result !== false)
        {
            $KodiResult = new Kodi_RPC_Data();
            $KodiResult->CreateFromJSONString($Result);
            $ret = $KodiResult->GetResult();
            if (is_a($ret, 'KodiRPCException'))
            {
                trigger_error('Error (' . $ret->getCode() . '): ' . $ret->getMessage(), E_USER_NOTICE);
            }
            if ($KodiResult->GetResult() == "pong")
                return true;
        }
        return false;
    }

    /**
     * Führt eine Anfrage an den Kodi-Webserver aus.
     * 
     * @param string $URI URI welche angefragt wird.
     * @return boolean|string Inhalt der Antwort, False bei Fehler.
     */
    private function DoWebserverRequest(string $URI)
    {
        $Host = $this->ReadPropertyString('Host');
        $Port = $this->ReadPropertyInteger('Webport');
        $UseBasisAuth = $this->ReadPropertyBoolean('BasisAuth');
        $User = $this->ReadPropertyString('Username');
        $Pass = $this->ReadPropertyInteger('Password');
        $URL = "http://" . $Host . ":" . $Port . $URI;
        $ch = curl_init();
        $timeout = 1; // 0 wenn kein Timeout
        curl_setopt($ch, CURLOPT_URL, $URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        if ($UseBasisAuth)
        {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $User . ':' . $Pass);
        }
        $this->SendDebug('DoWebrequest', $URL, 0);
        $Result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code >= 400)
        {
            $this->SendDebug('Webrequest Error', $http_code, 0);
            $Result = false;
        }
        else
            $this->SendDebug('Webrequest Result:' . $http_code, substr($Result, 0, 100), 0);
        curl_close($ch);
        return $Result;
    }

    /**
     * Öffnet oder schließt den übergeordneten IO-Parent
     * @param int $ParentID
     * @param bool $Open True für öffnen, false für schließen.
     */
    private function OpenIOParent(int $ParentID, bool $Open)
    {
        if ($ParentID == 0)
            return;
        IPS_SetProperty($ParentID, 'Open', $Open);
        if (IPS_HasChanges($ParentID))
            @IPS_ApplyChanges($ParentID);
    }

    /**
     * Aktiviert / Deaktiviert den WatchdogTimer.
     * 
     * @param bool $Active True für aktiv, false für deaktiv.
     */
    private function SetWatchdogTimer(bool $Active)
    {
        if ($this->ReadPropertyBoolean('Watchdog'))
        {
            $Interval = $this->ReadPropertyInteger('Interval');
            $Interval = ($Interval < 5) ? 0 : $Interval;
            if ($Active)
                $this->SetTimerInterval("Watchdog", $Interval * 1000);
            else
                $this->SetTimerInterval("Watchdog", 0);
        }
    }

    /**
     * Sendet ein PowerEvent an die Childs.
     * Ermöglicht es dass der Child vom Typ KodiDeviceSystem den aktuellen an/aus Zustand von Kodi kennt.
     * 
     * @access private
     * @param bool $value true für an, false für aus.
     */
    private function SendPowerEvent($value)
    {
        $KodiData = new Kodi_RPC_Data('System', 'Power', array('data' => $value), 0);
        $this->SendDataToDevice($KodiData);
        $KodiData = new Kodi_RPC_Data('Playlist', 'OnClear', array('data' => array('playlistid' => 0)), 0);
        $this->SendDataToDevice($KodiData);
        $KodiData = new Kodi_RPC_Data('Playlist', 'OnClear', array('data' => array('playlistid' => 1)), 0);
        $this->SendDataToDevice($KodiData);
        $KodiData = new Kodi_RPC_Data('Playlist', 'OnClear', array('data' => array('playlistid' => 2)), 0);
        $this->SendDataToDevice($KodiData);
    }

    /**
     * Dekodiert die empfangenen Events und Anworten auf 'GetProperties'.
     * 
     * @access protected
     * @param string $Method RPC-Funktion ohne Namespace
     * @param object $KodiPayload Der zu dekodierende Datensatz als Objekt.
     */
    protected function Decode($Method, $Event)
    {
        $this->SendDebug('KODI_Event', $Event, 0);
    }

    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     * 
     * @access private
     */
    private function GetParentData()
    {
        $OldParentId = $this->GetBuffer('Parent');
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($OldParentId > 0)
            $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
        if ($ParentId > 0)
        {
            $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            $this->SetBuffer('Parent', $ParentId);
        }
        else
            $this->SetBuffer('Parent', 0);
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'KODIRPC_GetImage'. Holt ein Bild vom Kodi-Webfront.
     * 
     * @access public
     * @param string $path Pfad des Bildes.
     * @result string Bildinhalt als Bytestring.
     */
    public function GetImage(string $path)
    {


        $CoverRAW = $this->DoWebserverRequest("/image/" . rawurlencode($path));

        /*        if ($CoverRAW === false)
          {
          $KodiData = new Kodi_RPC_Data('Files', 'PrepareDownload', array('path' => $path));
          $JSON = $KodiData->ToRawRPCJSONString();
          $Result = $this->DoWebserverRequest("/jsonrpc?request=" . rawurlencode($JSON));
          if ($Result !== false)
          {
          $KodiResult = new Kodi_RPC_Data();
          $KodiResult->CreateFromJSONString($Result);
          $ret = $KodiResult->GetResult();
          if (is_a($ret, 'KodiRPCException'))
          {
          trigger_error('Error (' . $ret->getCode() . '): ' . $ret->getMessage(), E_USER_NOTICE);
          return false;
          }
          $CoverRAW = $this->DoWebserverRequest("/".$ret->details->path);
          }
          } */

        if ($CoverRAW === false)
            trigger_error('Error on load image from Kodi.', E_USER_NOTICE);
        return $CoverRAW;
    }

    /**
     * IPS-Instanz-Funktion 'KODIRPC_KeepAlive'.
     * Sendet einen RPC-Ping an Kodi und prüft die erreichbarkeit.
     * 
     * @access public
     * @result bool true wenn Kodi erreichbar, sonst false.
     */
    public function KeepAlive()
    {
        $KodiData = new Kodi_RPC_Data('JSONRPC', 'Ping');
        $ret = @$this->Send($KodiData);
        if ($ret !== "pong")
        {
            trigger_error('Connection to Kodi lost.', E_USER_NOTICE);
            $this->SendPowerEvent(false);
            $this->SetStatus(203);
            return $this->ApplyChanges();
        }
        $this->SetStatus(IS_ACTIVE);

        return true;
    }

    /**
     * IPS-Instanz-Funktion 'KODIRPC_Watchdog'.
     * Sendet einen TCP-Ping an Kodi und prüft die erreichbarkeit des OS.
     * Wird erkannt, dass das OS erreichbar ist, wird versucht eine RPC-Verbindung zu Kodi aufzubauen.
     * 
     * @access public
     */
    public function Watchdog()
    {
        $ParentID = $this->GetParent();
        if ($ParentID > 0)
        {
            if (!@Sys_Ping($this->ReadPropertyString('Host'), 500))
                return;
            $Parent = IPS_GetInstance($ParentID);
            if ($Parent['InstanceStatus'] <> IS_ACTIVE)
            {
                $result = @IPS_ApplyChanges($ParentID);
                if ($result)
                    @IPS_ApplyChanges($this->InstanceID);
            }
        }
    }

################## DATAPOINTS DEVICE

    /**
     * Interne Funktion des SDK. Nimmt Daten von Childs entgegen und sendet Diese weiter.
     * 
     * @access public
     * @param string $JSONString Ein Kodi_RPC_Data-Objekt welches als JSONString kodiert ist.
     * @result bool true wenn Daten gesendet werden konnten, sonst false.
     */
    public function ForwardData($JSONString)
    {
//        $this->SendDebug('Forward', $JSONString, 0);

        $Data = json_decode($JSONString);
//        if ($Data->DataID <> "{0222A902-A6FA-4E94-94D3-D54AA4666321}")
//            return false;
        $KodiData = new Kodi_RPC_Data();
        $KodiData->CreateFromGenericObject($Data);
//        try
//        {
        $ret = $this->Send($KodiData);
        //          $this->SendDebug('Result', $anwser, 0);
        if (!is_null($ret))
            return serialize($ret);
//        }
//        catch (Exception $ex)
//        {
//            trigger_error($ex->getMessage(), $ex->getCode());
//        }
        return false;
    }

    /**
     * Sendet Kodi_RPC_Data an die Childs.
     * 
     * @access private
     * @param Kodi_RPC_Data $KodiData Ein Kodi_RPC_Data-Objekt.
     */
    private function SendDataToDevice(Kodi_RPC_Data $KodiData)
    {
        $Data = $KodiData->ToJSONString('{73249F91-710A-4D24-B1F1-A72F216C2BDC}');
        $this->SendDebug('IPS_SendDataToChildren', $Data, 0);
        $this->SendDataToChildren($Data);
    }

################## DATAPOINTS PARENT    

    /**
     * Empfängt Daten vom Parent.
     * 
     * @access public
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @result bool True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);

        // Datenstream zusammenfügen
        $head = $this->GetBuffer('BufferIN');
//        $this->SetBuffer('BufferIN', '');
        $Data = $head . utf8_decode($data->Buffer);

        // Stream in einzelne Pakete schneiden
        $Data = str_replace('}{', '}' . chr(0x04) . '{', $Data, $Count);
        $JSONLine = explode(chr(0x04), $Data);

        if (is_null(json_decode($JSONLine[$Count])))
        {
            // Rest vom Stream wieder in den Empfangsbuffer schieben
            $tail = array_pop($JSONLine);
            $this->SetBuffer('BufferIN', $tail);
        }
        else
            $this->SetBuffer('BufferIN', '');

        // Pakete verarbeiten
        foreach ($JSONLine as $JSON)
        {
            $KodiData = new Kodi_RPC_Data();
            $KodiData->CreateFromJSONString($JSON);
            if ($KodiData->Typ == Kodi_RPC_Data::$ResultTyp) // Reply
            {
                try
                {
                    $this->SendQueueUpdate($KodiData->Id, $KodiData);
                }
                catch (Exception $exc)
                {
                    $buffer = $this->GetBuffer('BufferIN');
                    $this->SetBuffer('BufferIN', $JSON . $buffer);
                    trigger_error($exc->getMessage(), E_USER_NOTICE);
                    continue;
                }
            }
            else if ($KodiData->Typ == Kodi_RPC_Data::$EventTyp) // Event
            {
                $this->SendDebug('KODI_Event', $KodiData, 0);
                $this->SendDataToDevice($KodiData);
                if (self::$Namespace == $KodiData->Namespace)
                    $this->Decode($KodiData->Method, $KodiData->GetEvent());
            }
        }
        return true;
    }

    /**
     * Versendet ein Kodi_RPC-Objekt und empfängt die Antwort.
     * 
     * @access protected
     * @param Kodi_RPC_Data $KodiData Das Objekt welches versendet werden soll.
     * @result mixed Enthält die Antwort auf das Versendete Objekt oder NULL im Fehlerfall.
     */
    protected function Send(Kodi_RPC_Data $KodiData)
    {
        try
        {
            if ($this->ReadPropertyBoolean('Open') === false)
                throw new Exception('Instance inactiv.', E_USER_NOTICE);

            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);
            $this->SendDebug('Send', $KodiData, 0);
            $this->SendQueuePush($KodiData->Id);
            $this->SendDataToParent($KodiData);
            $ReplyKodiData = $this->WaitForResponse($KodiData->Id);

            if ($ReplyKodiData === false)
            {
                //$this->SetStatus(IS_EBASE + 3);
                throw new Exception('No anwser from Kodi', E_USER_NOTICE);
            }

            $ret = $ReplyKodiData->GetResult();
            if (is_a($ret, 'KodiRPCException'))
            {
                throw $ret;
            }
            $this->SendDebug('Receive', $ReplyKodiData, 0);
            return $ret;
        }
        catch (KodiRPCException $ex)
        {
            $this->SendDebug("Receive", $ex, 0);
            trigger_error('Error (' . $ex->getCode() . '): ' . $ex->getMessage(), E_USER_NOTICE);
        }
        catch (Exception $ex)
        {
            $this->SendDebug("Receive", $ex->getMessage(), 0);
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return NULL;
    }

    /**
     * Sendet ein Kodi_RPC-Objekt an den Parent.
     * 
     * @access protected
     * @param Kodi_RPC_Data $Data Das Objekt welches versendet werden soll.
     * @result bool true
     */
    protected function SendDataToParent($Data)
    {
        $JsonString = $Data->ToRPCJSONString('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}');
        parent::SendDataToParent($JsonString);
        return true;
    }

    /**
     * Wartet auf eine RPC-Antwort.
     * 
     * @access private
     * @param int $Id Die RPC-ID auf die gewartet wird.
     * @result mixed Enthält ein Kodi_RPC_Data-Objekt mit der Antwort, oder false bei einem Timeout.
     */
    private function WaitForResponse($Id)
    {
        for ($i = 0; $i < 1000; $i++)
        {
            if ($this->GetBuffer('ReplyJSONData') === 'a:0:{}') // wenn wenig los, gleich warten            
                IPS_Sleep(5);
            else
            {
                $ret = unserialize($this->GetBuffer('ReplyJSONData'));
                if (!array_key_exists(intval($Id), $ret))
                    return false;
                if ($ret[$Id] <> "")
                    return $this->SendQueuePop($Id);
                IPS_Sleep(5);
            }
        }
        $this->SendQueueRemove($Id);
        return false;
    }

################## SENDQUEUE

    /**
     * Fügt eine Anfrage in die SendQueue ein.
     * 
     * @access private
     * @param int $Id die RPC-ID des versendeten RPC-Objektes.
     */
    private function SendQueuePush(int $Id)
    {
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize($this->GetBuffer('ReplyJSONData'));
        $data[$Id] = "";
        $this->SetBuffer('ReplyJSONData', serialize($data));
        $this->unlock('ReplyJSONData');
    }

    /**
     * Fügt eine RPC-Antwort in die SendQueue ein.
     * 
     * @access private
     * @param int $Id die RPC-ID des empfangenen Objektes.
     * @param Kodi_RPC_Data $KodiData Das empfangene RPC-Result.
     */
    private function SendQueueUpdate(int $Id, Kodi_RPC_Data $KodiData)
    {
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize($this->GetBuffer('ReplyJSONData'));
        $data[$Id] = $KodiData->ToJSONString("");
        $this->SetBuffer('ReplyJSONData', serialize($data));
        $this->unlock('ReplyJSONData');
    }

    /**
     * Holt eine RPC-Antwort aus der SendQueue.
     * 
     * @access private
     * @param int $Id die RPC-ID des empfangenen Objektes.
     * @return Kodi_RPC_Data Das empfangene RPC-Result.
     */
    private function SendQueuePop(int $Id)
    {
        $data = unserialize($this->GetBuffer('ReplyJSONData'));
        $Result = new Kodi_RPC_Data();
        $JSONObject = json_decode($data[$Id]);
        $Result->CreateFromGenericObject($JSONObject);
        $this->SendQueueRemove($Id);
        return $Result;
    }

    /**
     * Löscht einen RPC-Eintrag aus der SendQueue.
     * 
     * @access private
     * @param int $Id Die RPC-ID des zu löschenden Objektes.
     */
    private function SendQueueRemove(int $Id)
    {
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize($this->GetBuffer('ReplyJSONData'));
        unset($data[$Id]);
        $this->SetBuffer('ReplyJSONData', serialize($data));
        $this->unlock('ReplyJSONData');
    }

################## DUMMYS / WORKAROUNDS - protected

    /**
     * Formatiert eine DebugAusgabe und gibt sie an IPS weiter.
     *
     * @access protected
     * @param string $Message Nachrichten-Feld.
     * @param string|array|Kodi_RPC_Data $Data Daten-Feld.
     * @param int $Format Ausgabe in Klartext(0) oder Hex(1)
     */
    protected function SendDebug($Message, $Data, $Format)
    {
        if (is_a($Data, 'Kodi_RPC_Data'))
        {
            switch ($Data->Typ)
            {
                case Kodi_RPC_Data::$EventTyp:
                    $this->SendDebug($Message . " Event", $Data->GetEvent(), 0);
                    break;
                case Kodi_RPC_Data::$ResultTyp:
                    $this->SendDebug($Message . " Result", $Data->GetResult(), 0);
                    break;
                default:
                    parent::SendDebug($Message . " Method", $Data->Namespace . '.' . $Data->Method, 0);
                    $this->SendDebug($Message . " Params", $Data->Params, 0);
                    break;
            }
        }
        else if (is_a($Data, 'KodiRPCException'))
        {
            parent::SendDebug('Error', $Data->getCode() . ' : ' . $Data->getMessage(), 0);
        }
        elseif (is_array($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        }
        else if (is_object($Data))
        {
            foreach ($Data as $Key => $DebugData)
            {
                $this->SendDebug($Message . ":" . $Key, $DebugData, 0);
            }
        }
        else
        {
            parent::SendDebug($Message, $Data, $Format);
        }
    }

    /**
     * Liefert den Parent der Instanz.
     * 
     * @return int|bool InstanzID des Parent, false wenn kein Parent vorhanden.
     */
    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     * 
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $ParentID = $this->GetParent();
        if ($ParentID !== false)
        {
            if (IPS_GetInstance($ParentID)['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }

    /**
     * Erzeugt einen neuen Parent, wenn keiner vorhanden ist.
     * 
     * @param string $ModuleID Die GUID des benötigten Parent.
     */
    protected function RequireParent($ModuleID)
    {
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] == 0)
        {
            $parentID = IPS_CreateInstance($ModuleID);
            $instance = IPS_GetInstance($parentID);
            IPS_SetName($parentID, "Kodi JSONRPC TCP-Socket");
            IPS_ConnectInstance($this->InstanceID, $parentID);
        }
    }

    /**
     * Löscht einen Timer.
     * 
     * @param string $Name Ident des Timers
     */
    protected function UnregisterTimer(string $Name)
    {
        $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
        if ($id > 0)
        {
            if (IPS_EventExists($id))
                IPS_DeleteEvent($id);
        }
    }

    /**
     * Setzt den Status dieser Instanz auf den übergebenen Status.
     * Prüft vorher noch ob sich dieser vom aktuellen Status unterscheidet.
     * 
     * @param int $InstanceStatus
     */
    protected function SetStatus($InstanceStatus)
    {
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }

################## SEMAPHOREN Helper  - private  

    /**
     * Setzt einen 'Lock'.
     *      * 
     * @param string $ident Ident der Semaphore
     * @return bool True bei Erfolg, false bei Misserfolg.
     */
    private function lock(string $ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("KODI_" . (string) $this->InstanceID . (string) $ident, 1))
                return true;
            else
                IPS_Sleep(mt_rand(1, 5));
        }
        return false;
    }

    /**
     * Löscht einen 'Lock'.
     * 
     * @param string $ident Ident der Semaphore
     */
    private function unlock(string $ident)
    {
        IPS_SemaphoreLeave("KODI_" . (string) $this->InstanceID . (string) $ident);
    }

}

/** @} */
?>