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
 * KodiSplitter Klasse für die Kommunikation mit der Kodi-RPC-Api.
 * Enthält außerdem den Namespace JSONRPC.
 * Erweitert IPSModule.
 * 
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
        $this->RegisterPropertyBoolean("Watchdog", false);
        $this->RegisterPropertyInteger("Interval", 5);
    }

    /**
     * Interne Funktion des SDK.
     * 
     * @access public
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Zwangskonfiguration des ClientSocket
        $Open = $this->ReadPropertyBoolean('Open');
        $NewState = IS_ACTIVE;
        //IPS_LogMessage('Kodi', '1');
        if (!$Open)
        {
            $NewState = IS_INACTIVE;
            //IPS_LogMessage('Kodi', '2');
        }
        if ($this->ReadPropertyString('Host') == '')
        {
            //IPS_LogMessage('Kodi', '3');

            if ($Open)
            {
                //IPS_LogMessage('Kodi', '4');

                $NewState = IS_EBASE + 2;
                $Open = false;
            }
        }

        if ($this->ReadPropertyString('Port') == '')
        {
            //IPS_LogMessage('Kodi', '5');

            if ($Open)
            {
                //IPS_LogMessage('Kodi', '6');

                $NewState = IS_EBASE + 2;
                $Open = false;
            }
        }
        $ParentID = $this->GetParent();

        if ($ParentID > 0)
        {
            //IPS_LogMessage('Kodi', '7');

            if (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('Host'))
            {
                //IPS_LogMessage('Kodi', '8');

                IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('Host'));
//                $ChangeParentSetting = true;
            }
            if (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port'))
            {
                //IPS_LogMessage('Kodi', '8a');

                IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
//                $ChangeParentSetting = true;
            }
            // Keine Verbindung erzwingen wenn Host offline ist
            if ($Open)
            {
                //IPS_LogMessage('Kodi', '9');

                $Open = @Sys_Ping($this->ReadPropertyString('Host'), 500);
                if (!$Open)
                {
                    //IPS_LogMessage('Kodi', '10');

                    $NewState = IS_EBASE + 3;
                }
            }
            if (IPS_GetProperty($ParentID, 'Open') <> $Open)
            {
                //IPS_LogMessage('Kodi', '11');

                IPS_SetProperty($ParentID, 'Open', $Open);
//                $ChangeParentSetting = true;
            }
            if (IPS_HasChanges($ParentID))
            {
                //IPS_LogMessage('Kodi', '12');

                @IPS_ApplyChanges($ParentID);
            }
        }
        else
        {
            //IPS_LogMessage('Kodi', '13');

            if ($Open)
            {
                //IPS_LogMessage('Kodi', '14');

                $NewState = IS_INACTIVE;
                $Open = false;
            }
        }
        // Eigene Profile
        $BufferINID = $this->RegisterVariableString("BufferIN", "BufferIN", "", -1);
        IPS_SetHidden($BufferINID, true);
        SetValueString($BufferINID, "");

        $ReplyJSONDataID = $this->RegisterVariableString("ReplyJSONData", "ReplyJSONData", "", -3);
        IPS_SetHidden($ReplyJSONDataID, true);
        SetValueString($ReplyJSONDataID, "");

        $this->RegisterTimer('KeepAlive', 0, 'KODIRPC_KeepAlive($_IPS[\'TARGET\']);');
        if ($this->ReadPropertyBoolean('Watchdog'))
            $this->RegisterTimer('Watchdog', 0, 'KODIRPC_Watchdog($_IPS[\'TARGET\']);');
        else
            $this->UnregisterTimer('Watchdog');
//        
        // Wenn wir verbunden sind,  mit Kodi, dann anmelden für Events

        if (($Open)
                and ( $this->HasActiveParent($ParentID)))
        {
            //IPS_LogMessage('Kodi', '20');

            switch (IPS_GetKernelRunlevel())
            {
                case KR_READY:
                    //IPS_LogMessage('Kodi', '21');
//                    if ($NewState == IS_ACTIVE)
//                    {
                    $KodiData = new Kodi_RPC_Data('JSONRPC', 'Ping');
                    $ret = $this->Send($KodiData);
                    if ($ret == "pong")
                    {
                        //IPS_LogMessage('Kodi', '22');

                        $this->SendPowerEvent(true);
                        $WatchdogTimer = 0;
                        $this->SetTimerInterval("KeepAlive", 60 * 1000);

                        $InstanceIDs = IPS_GetInstanceList();
                        foreach ($InstanceIDs as $IID)
                            if (IPS_GetInstance($IID)['ConnectionID'] == $this->InstanceID)
                                @IPS_ApplyChanges($IID);
                    }
                    else
                    {
                        if (IPS_GetProperty($ParentID, 'Open'))
                        {
                            //IPS_LogMessage('Kodi', '30');
                            IPS_SetProperty($ParentID, 'Open', false);
                            @IPS_ApplyChanges($ParentID);
                        }

                        //IPS_LogMessage('Kodi', '23');
                        $this->SendPowerEvent(false);
                        $WatchdogTimer = $this->ReadPropertyInteger('Interval');
                        $this->SetTimerInterval("KeepAlive", 0);
                        $NewState = IS_EBASE + 3;
                    }
                    break;
                case KR_INIT:
                    //IPS_LogMessage('Kodi', '24');

                    if ($NewState == IS_ACTIVE)
                    {
                        //IPS_LogMessage('Kodi', '25');

                        $NewState = IS_EBASE + 3;
                    }
                    break;
            }
        }
        else
        {
            //IPS_LogMessage('Kodi', '26');

            if ($this->ReadPropertyBoolean('Open'))
            {
                //IPS_LogMessage('Kodi', '27');

                if (!$this->HasActiveParent($ParentID))
                {
                    //IPS_LogMessage('Kodi', '28');

                    if ($NewState == IS_EBASE + 2)
                        $WatchdogTimer = 0;
                    else
                    {
                        //IPS_LogMessage('Kodi', '29');
                        $WatchdogTimer = $this->ReadPropertyInteger('Interval');
                    }

                    if (IPS_GetProperty($ParentID, 'Open'))
                    {
                        //IPS_LogMessage('Kodi', '30');
                        IPS_SetProperty($ParentID, 'Open', false);
                        @IPS_ApplyChanges($ParentID);
                    }
                }
            }
            else
            {
                //IPS_LogMessage('Kodi', '31');

                $WatchdogTimer = 0;
            }
            $this->SendPowerEvent(false);
            $this->SetTimerInterval("KeepAlive", 0);
        }
        $this->SetStatus($NewState);

        if ($this->ReadPropertyBoolean('Watchdog'))
        {
            //IPS_LogMessage('Kodi', '32');

            if ($WatchdogTimer >= 5)
            {
                //IPS_LogMessage('Kodi', '33');

                $this->SetTimerInterval("Watchdog", $WatchdogTimer * 1000);
            }
            else
            {
                //IPS_LogMessage('Kodi', '34');

                $this->SetTimerInterval("Watchdog", 0);
            }
        }
    }

################## PRIVATE     

    /**
     * Sendet ein PowerEvent an die Childs.
     * Ermöglicht es dass der Child vom Typ KodiDeviceSystem den aktuellen an/aus Zustand von Kodi kennt.
     * 
     * @access private
     * @param boolean $value true für an, false für aus.
     */
    private function SendPowerEvent($value)
    {
        $KodiData = new Kodi_RPC_Data('System', 'Power', array('data' => $value), 0);
        IPS_LogMessage('KODI_PWR_Event', print_r($KodiData, true));
        $this->SendDataToDevice($KodiData);
    }

    protected function Decode($Method, $Event)
    {
        IPS_LogMessage('KODI_Event:' . $Method, print_r($Event, true));
    }

################## PUBLIC

    public function RawSend(string $Namespace, string $Method, $Params)
    {
        $KodiData = new Kodi_RPC_Data($Namespace, $Method, $Params);
        $ret = $this->Send($KodiData);
        return $ret;
    }

    /**
     * IPS-Instanz-Funktion 'KODIRPC_GetImage'. Holt ein Bild vom Kodi-Webfront.
     * 
     * @access public
     * @param string $path Pfad des Bildes.
     * @result string Bildinhalt als Bytestring.
     */
    public function GetImage(string $path)
    {
        $Host = $this->ReadPropertyString('Host');
        $Port = $this->ReadPropertyInteger('Webport');
        $CoverURL = "http://" . $Host . ":" . $Port . "/image/" . urlencode($path);
        $ch = curl_init();
        $timeout = 1; // 0 wenn kein Timeout
        curl_setopt($ch, CURLOPT_URL, $CoverURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $CoverRAW = curl_exec($ch);
        curl_close($ch);
        if ($CoverRAW === false)
            trigger_error('Error on load image from Kodi.', E_USER_NOTICE);
        return $CoverRAW;
    }

    /**
     * IPS-Instanz-Funktion 'KODIRPC_KeepAlive'.
     * Sendet einen RPC-Ping an Kodi und prüft die erreichbarkeit.
     * 
     * @access public
     * @result boolean true wenn Kodi erreichbar, sonst false.
     */
    public function KeepAlive()
    {
        $KodiData = new Kodi_RPC_Data('JSONRPC', 'Ping');
        $ret = $this->Send($KodiData);
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
        $this->SetTimerInterval("Watchdog", 0);
        $ParentID = $this->GetParent();
        if ($ParentID > 0)
        {
            if (!@Sys_Ping($this->ReadPropertyString('Host'), 500))
            {
                $this->SendPowerEvent(false);
                $this->SetStatus(203);
                $WatchdogTimer = $this->ReadPropertyInteger('Interval');
                $this->SetTimerInterval("Watchdog", $WatchdogTimer * 1000);
                return;
            }
            $Parent = IPS_GetInstance($ParentID);
            if ($Parent['InstanceStatus'] <> IS_ACTIVE)
            {
                $result = @IPS_ApplyChanges($ParentID);
                if ($result)
                    @IPS_ApplyChanges($this->InstanceID);
            }
        }
    }

################## DATAPOINT RECEIVE FROM CHILD

    /**
     * Interne Funktion des SDK. Nimmt Daten von Childs entgegen und sendet Diese weiter.
     * 
     * @access public
     * @param string $JSONString Ein Kodi_RPC_Data-Objekt welches als JSONString kodiert ist.
     * @result boolean true wenn Daten gesendet werden konnten, sonst false.
     */
    public function ForwardData($JSONString)
    {
//        IPS_LogMessage("forward", $JSONString);
        $Data = json_decode($JSONString);
        if ($Data->DataID <> "{0222A902-A6FA-4E94-94D3-D54AA4666321}")
            return false;
        $KodiData = new Kodi_RPC_Data();
        $KodiData->CreateFromGenericObject($Data);
//        IPS_LogMessage("forward", print_r($KodiData, true));

        try
        {
//            $this->ForwardDataToParent($KodiData);
            $anwser = $this->Send($KodiData);
            if (!is_null($anwser))
                return serialize($anwser);
        }
        catch (Exception $ex)
        {
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return false;
    }

################## DATAPOINTS DEVICE

    /**
     * Sendet Kodi_RPC_Data an den Parent.
     * 
     * @access private
     * @param Kodi_RPC_Data $KodiData Ein Kodi_RPC_Data-Objekt.
     */
//    private function ForwardDataToParent(Kodi_RPC_Data $KodiData)
//    {
//        try
//        {
//            $this->SendDataToParent($KodiData);
//        }
//        catch (Exception $ex)
//        {
//            throw new Exception($ex->getMessage(), $ex->getCode());
//        }
//    }

    /**
     * Sendet Kodi_RPC_Data an die Childs.
     * 
     * @access private
     * @param Kodi_RPC_Data $KodiData Ein Kodi_RPC_Data-Objekt.
     */
    private function SendDataToDevice(Kodi_RPC_Data $KodiData)
    {
//        IPS_LogMessage('SendDataToZone',print_r($APIData,true));
//        IPS_LogMessage("Kodi-Splitter", print_r($KodiData, true));
        $Data = $KodiData->ToJSONString('{73249F91-710A-4D24-B1F1-A72F216C2BDC}');
//        IPS_LogMessage("Kodi-Splitter", print_r($Data, true));
        $this->SendDataToChildren($Data);
        //IPS_SendDataToChildren($this->InstanceID, $Data);
    }

################## SENDQUEUE

    private function SendQueuePush(integer $Id)
    {
        $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize(GetValueString($ReplyJSONDataID));
        $data[$Id] = "";
        SetValueString($ReplyJSONDataID, serialize($data));
        $this->unlock('ReplyJSONData');
    }

    private function SendQueueUpdate(integer $Id, Kodi_RPC_Data $KodiData)
    {
        $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize(GetValueString($ReplyJSONDataID));
        $data[$Id] = $KodiData->ToJSONString("");
        SetValueString($ReplyJSONDataID, serialize($data));
        $this->unlock('ReplyJSONData');
    }

    private function SendQueuePop(integer $Id)
    {
        $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
        $data = unserialize(GetValueString($ReplyJSONDataID));
        $Result = new Kodi_RPC_Data();
        $JSONObject = json_decode($data[$Id]);
        $Result->CreateFromGenericObject($JSONObject);
        //IPS_LogMessage('SendQueuePop', print_r($Result, true));

        $this->SendQueueRemove($Id);
        return $Result;
    }

    private function SendQueueRemove(integer $Id)
    {
        $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
        if (!$this->lock('ReplyJSONData'))
            throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
        $data = unserialize(GetValueString($ReplyJSONDataID));
        unset($data[$Id]);
        SetValueString($ReplyJSONDataID, serialize($data));
        $this->unlock('ReplyJSONData');
    }

################## DATAPOINTS PARENT    

    /**
     * Empfängt Daten vom Parent.
     * 
     * @access public
     * @param string $JSONString Das empfangene JSON-kodierte Objekt vom Parent.
     * @result boolean True wenn Daten verarbeitet wurden, sonst false.
     */
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $bufferID = $this->GetIDForIdent("BufferIN");

        // Empfangs Lock setzen
        if (!$this->lock("bufferin"))
        {
            trigger_error("ReceiveBuffer is locked", E_USER_WARNING);
            return false;
        }

        // Datenstream zusammenfügen
        $head = GetValueString($bufferID);
        SetValueString($bufferID, '');
        //IPS_LogMessage('Buffer', print_r($data, true));
        $Data = $head . utf8_decode($data->Buffer);

        // Stream in einzelne Pakete schneiden
        $Data = str_replace('}{', '}' . chr(0x04) . '{', $Data, $Count);
        $JSONLine = explode(chr(0x04), $Data);

        if (is_null(json_decode($JSONLine[$Count])))
        {
            // Rest vom Stream wieder in den Empfangsbuffer schieben
            $tail = array_pop($JSONLine);
            SetValueString($bufferID, $tail);
        }
        else
            SetValueString($bufferID, '');

        // Empfangs Lock aufheben
        $this->unlock("bufferin");

        // Pakete verarbeiten
        foreach ($JSONLine as $JSON)
        {
            $KodiData = new Kodi_RPC_Data();
            $KodiData->CreateFromJSONString($JSON);
            //IPS_LogMessage("receive", print_r($KodiData, true));
            if ($KodiData->Typ == Kodi_RPC_Data::$ResultTyp) // Reply
            {
                try
                {
                    $this->SendQueueUpdate($KodiData->Id, $KodiData);
                }
                catch (Exception $ex)
                {
                    trigger_error($exc->getMessage(), E_USER_NOTICE);
                }
            }
            else if ($KodiData->Typ == Kodi_RPC_Data::$EventTyp) // Event
            {
                ob_start();
                var_dump($KodiData);
                $dump = ob_get_clean();
                IPS_LogMessage('KODI_Event', $dump);
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
            if (!$this->HasActiveParent())
                throw new Exception('Intance has no active parent.', E_USER_NOTICE);

            $this->SendQueuePush($KodiData->Id);
            $this->SendDataToParent($KodiData);
            $ReplayKodiData = $this->WaitForResponse($KodiData->Id);
            //IPS_LogMessage("ReplayKodiData", print_r($ReplayKodiData, true));

            if ($ReplayKodiData === false)
                throw new Exception('No anwser from Kodi', E_USER_NOTICE);

            $ret = $ReplayKodiData->GetResult();
            if (is_a($ret, 'KodiRPCException'))
            {
                throw $ret;
            }
            return $ret;
        }
        catch (KodiRPCException $ex)
        {
            trigger_error('Error (' . $ex->getCode() . '): ' . $ex->getMessage(), E_USER_NOTICE);
        }
        catch (Exception $ex)
        {
            trigger_error($ex->getMessage(), $ex->getCode());
        }
        return NULL;
    }

    /**
     * Sendet ein Kodi_RPC-Objekt an den Parent.
     * 
     * @access protected
     * @param Kodi_RPC_Data $Data Das Objekt welches versendet werden soll.
     * @result boolean true
     */
    protected function SendDataToParent($Data)
    {
        if (!$this->HasActiveParent())
            throw new Exception("Instance has no active Parent.", E_USER_NOTICE);

        $JsonString = $Data->ToRPCJSONString('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}');
        parent::SendDataToParent($JsonString);
        return true;
    }

    /**
     * Wartet wuf eine RPC-Antwort.
     * 
     * @access private
     * @param integer $Id Die RPC-ID auf die gewartet wird.
     * @result mixed Enthält ein Kodi_RPC_Data-Objekt mit der Antwort, oder false bei einem Timeout.
     */
    private function WaitForResponse($Id)
    {
        $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
        for ($i = 0; $i < 1000; $i++)
        {
            if (GetValueString($ReplyJSONDataID) === 'a:0:{}') // wenn wenig los, gleich warten
                IPS_Sleep(5);
            else
            {
                $ret = unserialize(GetValueString($ReplyJSONDataID));
                if (!array_key_exists(intval($Id), $ret))
                {
                    //                  IPS_LogMessage('SendQueue', "notfound");
                    return false;
                }
                if ($ret[$Id] <> "")
                {
//                    IPS_LogMessage('SendQueue', "found");
                    return $this->SendQueuePop($Id);
                }
                IPS_Sleep(5);
            }
        }
        return false;
    }

################## DUMMYS / WORKAROUNDS - protected

    /**
     * Liefert den Parent der Instanz.
     * 
     * @return integer|boolean InstanzID des Parent, false wenn kein Parent vorhanden.
     */
    protected function GetParent()
    {
        $instance = IPS_GetInstance($this->InstanceID);
        return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
    }

    /**
     * Prüft den Parent auf vorhandensein und Status.
     * 
     * @return boolean True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
//        IPS_LogMessage(__CLASS__, __FUNCTION__); //          
        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
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
     * @param integer $InstanceStatus
     */
    protected function SetStatus($InstanceStatus)
    {
        //IPS_LogMessage('NewState', $InstanceStatus);
        //IPS_LogMessage('NewState', IPS_GetInstance($this->InstanceID)['InstanceStatus']);

        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }

################## SEMAPHOREN Helper  - private  

    /**
     * Setzt einen 'Lock'.
     *      * 
     * @param string $ident Ident der Semaphore
     * @return boolean True bei Erfolg, false bei Misserfolg.
     */
    private function lock(string $ident)
    {
        for ($i = 0; $i < 100; $i++)
        {
            if (IPS_SemaphoreEnter("KODI_" . (string) $this->InstanceID . (string) $ident, 1))
            {
                return true;
            }
            else
            {
                IPS_Sleep(mt_rand(1, 5));
            }
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