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
        $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}", "Kodi JSONRPC TCP-Socket");
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
        $this->RegisterVariableString("BufferIN", "BufferIN", "", -1);
        IPS_SetHidden($this->GetIDForIdent('BufferIN'), true);
        $this->RegisterVariableString("ReplyJSONData", "ReplyJSONData", "", -3);
        IPS_SetHidden($this->GetIDForIdent('ReplyJSONData'), true);

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
        $KodiData = new Kodi_RPC_Data('System', 'Power', array("data" => $value), 0);
        $this->SendDataToDevice($KodiData);
    }

################## PUBLIC
    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:
     */

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
        $Data = json_decode($JSONString);
        if ($Data->DataID <> "{0222A902-A6FA-4E94-94D3-D54AA4666321}")
            return false;
        $KodiData = new Kodi_RPC_Data();
        $KodiData->GetDataFromJSONKodiObject($Data);
        try
        {
            $this->ForwardDataToParent($KodiData);
        }
        catch (Exception $ex)
        {
            trigger_error($ex->getMessage(), $ex->getCode());
            return false;
        }
        return true;
    }

################## DATAPOINTS DEVICE

    /**
     * Sendet Kodi_RPC_Data an den Parent.
     * 
     * @access private
     * @param Kodi_RPC_Data $KodiData Ein Kodi_RPC_Data-Objekt.
     */
    private function ForwardDataToParent(Kodi_RPC_Data $KodiData)
    {
        try
        {
            $this->SendDataToParent($KodiData);
        }
        catch (Exception $ex)
        {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * Sendet Kodi_RPC_Data an die Childs.
     * 
     * @access private
     * @param Kodi_RPC_Data $KodiData Ein Kodi_RPC_Data-Objekt.
     */
    private function SendDataToDevice(Kodi_RPC_Data $KodiData)
    {
//        IPS_LogMessage('SendDataToZone',print_r($APIData,true));
        $Data = $KodiData->ToKodiObjectJSONString('{73249F91-710A-4D24-B1F1-A72F216C2BDC}');
        IPS_SendDataToChildren($this->InstanceID, $Data);
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
            $KodiData->GetDataFromJSONIPSObject($JSON);
            $this->SendDataToDevice($KodiData);
            if (!is_null($KodiData->Id)) //Reply
            {
                $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');
                if (!$this->lock('ReplyJSONData'))
                    throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
                SetValueString($ReplyJSONDataID, $KodiData->ToKodiObjectJSONString(''));
                $this->unlock('ReplyJSONData');
            } else
            {
                if ($KodiData->Namespace == self::$Namespace)
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

            $ReplyJSONDataID = $this->GetIDForIdent('ReplyJSONData');

            if (!$this->lock('RequestSendData'))
                throw new Exception('RequestSendData is locked', E_USER_NOTICE);

            if (!$this->lock('ReplyJSONData'))
            {
                $this->unlock('ReplyJSONData');
                throw new Exception('ReplyJSONData is locked', E_USER_NOTICE);
            }
            SetValueString($ReplyJSONDataID, '');
            $this->unlock('ReplyJSONData');

            $ret = $this->SendDataToParent($KodiData);
            if ($ret === false)
            {
                $this->unlock('RequestSendData');
                throw new Exception('Instance has no active Parent Instance!', E_USER_NOTICE);
            }
            $ReplayKodiData = $this->WaitForResponse($KodiData->Id);
            if ($ReplayKodiData === false)
            {
                $this->unlock('RequestSendData');
                throw new Exception('No anwser from Kodi', E_USER_NOTICE);
            }
            $this->unlock('RequestSendData');
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

        $JsonString = $Data->ToIPSJSONString('{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}');
        IPS_SendDataToParent($this->InstanceID, $JsonString);
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
        for ($i = 0; $i < 300; $i++)
        {
            if (GetValueString($ReplyJSONDataID) === '')
                IPS_Sleep(5);
            else
            {
                if ($this->lock('ReplyJSONData'))
                {
                    $ret = GetValueString($ReplyJSONDataID);
                    SetValueString($ReplyJSONDataID, '');
                    $this->unlock('ReplyJSONData');
                    $JSON = json_decode($ret);
                    $Kodi_Data = new Kodi_RPC_Data();
                    $Kodi_Data->GetDataFromJSONKodiObject($JSON);
                    if ($Id == $Kodi_Data->Id)
                        return $Kodi_Data;
                    else
                    {
                        $i = $i - 100;
                        if ($i < 0)
                            $i = 0;
                    }
                }
            }
        }
        return false;
    }

################## DUMMYS / WOARKAROUNDS - protected
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
     * @param string $Name Der Name des Parent, wenn dieser neu angelegt wird.
     */
    protected function RequireParent(string $ModuleID, string $Name = null)
    {

        $instance = IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] == 0)
        {

            $parentID = IPS_CreateInstance($ModuleID);
            $instance = IPS_GetInstance($parentID);
            if ((!is_null($Name)) && ($Name <> ''))
                IPS_SetName($parentID, $Name);
            else
                IPS_SetName($parentID, $instance['ModuleInfo']['ModuleName']);
            IPS_ConnectInstance($this->InstanceID, $parentID);
        }
    }

    /*
      protected function RegisterTimer($Name, $Interval, $Script)
      {
      $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
      if ($id === false)
      $id = 0;
      if ($id > 0)
      {
      if (!IPS_EventExists($id))
      throw new Exception("Ident with name " . $Name . " is used for wrong object type", E_USER_NOTICE);

      if (IPS_GetEvent($id)['EventType'] <> 1)
      {
      IPS_DeleteEvent($id);
      $id = 0;
      }
      }
      if ($id == 0)
      {
      $id = IPS_CreateEvent(1);
      IPS_SetParent($id, $this->InstanceID);
      IPS_SetIdent($id, $Name);
      if ($Interval > 0)
      {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
      IPS_SetEventActive($id, true);
      }
      else
      {
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, 1);
      IPS_SetEventActive($id, false);
      }
      }
      IPS_SetName($id, $Name);
      IPS_SetHidden($id, true);
      IPS_SetEventScript($id, $Script);
      }
     */

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

    /*
      protected function SetTimerInterval($Name, $Interval)
      {
      $id = @IPS_GetObjectIDByIdent($Name, $this->InstanceID);
      if ($id === false)
      throw new Exception('Timer not present', E_USER_WARNING);
      if (!IPS_EventExists($id))
      throw new Exception('Timer not present', E_USER_WARNING);
      $Event = IPS_GetEvent($id);
      if ($Interval < 1)
      {
      if ($Event['EventActive'])
      IPS_SetEventActive($id, false);
      }
      else
      {
      if ($Event['CyclicTimeValue'] <> $Interval)
      IPS_SetEventCyclic($id, 0, 0, 0, 0, 1, $Interval);
      if (!$Event['EventActive'])
      IPS_SetEventActive($id, true);
      }
      } */

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