<?

require_once(__DIR__ . "/../KodiClass.php");  // diverse Klassen

class KodiDeviceSystem extends KodiBase
{

    static $Namespace = 'System';
    static $Properties = array(
        "canshutdown",
        "canhibernate",
        "cansuspend",
        "canreboot"
    );

    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('PowerScript', 0);
        $this->RegisterPropertyInteger('PowerOff', 0);
        $this->RegisterPropertyInteger('PreSelectScript', 0);
        $this->RegisterPropertyString('MACAddress', 0);
    }

    public function ApplyChanges()
    {
        switch ($this->ReadPropertyInteger('PreSelectScript'))
        {
            case 0:
                $ID = 0;
                break;
            case 1:
                $ID = $this->RegisterScript('WOLScript', 'Playlist Config', $this->CreateWOLScript(), -1);
                break;
            case 2:
                $ID = $this->RegisterScript('WOLScript', 'Playlist Config', $this->CreateFBPScript(), -1);
                break;
        }
        if ($ID > 0)
        {
            IPS_SetHidden($ID, true);
            IPS_SetProperty($this->InstanceID,'PowerScript', $ID);
            IPS_SetProperty($this->InstanceID, 'PreSelectScript', 0);
            IPS_Applychanges($this->InstanceID);
            return true;
        }
        $this->RegisterVariableBoolean("Power", "Power", "~Switch", 0);
        $this->EnableAction("Power");
        $this->RegisterVariableInteger("shutdown", "Herunterfahren", "Action.Kodi", 4);
        $this->EnableAction("shutdown");
        $this->RegisterVariableInteger("hibernate", "Ruhezustand", "Action.Kodi", 2);
        $this->EnableAction("hibernate");
        $this->RegisterVariableInteger("suspend", "Standby", "Action.Kodi", 1);
        $this->EnableAction("suspend");
        $this->RegisterVariableInteger("reboot", "Neustart", "Action.Kodi", 3);
        $this->EnableAction("reboot");
        $this->RegisterVariableInteger("ejectOpticalDrive", "Laufwerk öffnen", "Action.Kodi", 5);
        $this->EnableAction("ejectOpticalDrive");
//Never delete this line!
        parent::ApplyChanges();
    }

################## PRIVATE     

    private function GetMac()
    {
        $Address = $this->ReadPropertyString('MACAddress');
        $Address = str_replace('-', '', $Address);
        $Address = str_replace(':', '', $Address);
        if (strlen($Address) == 12)
            return $Address . '"';
        return '00AABB112233" /* Platzhalter für richtige Adresse */';
    }

    private function CreateFBPScript()
    {
        $Script = '<?
$mac = "' . $this->GetMac() . ' ;
$FBScript = 0;  /* Hier die ID von dem Script [FritzBox Project\Scripte\Aktions & Auslese-Script Host] eintragen */

if ($_IPS["SENDER"] <> "Kodi.System")
{
	echo "Dieses Script kann nicht direkt ausgeführt werden!";
	return;
}
   echo IPS_RunScriptWaitEx ($FBScript,array("SENDER"=>"RequestAction","IDENT"=>$mac,"VALUE"=>true));
?>';
        return $Script;
    }

    private function CreateWOLScript()
    {
        $Script = '<?
$mac = "' . $this->GetMac() . ' ;
if ($_IPS["SENDER"] <> "Kodi.System")
{
	echo "Dieses Script kann nicht direkt ausgeführt werden!";
	return;
}

$ip = "255.255.255.255"; // Broadcast adresse
return wake($ip,$mac);

function wake($ip, $mac,)
{
  $nic = fsockopen("udp://" . $ip, 15);
  if($nic)
  {
    $packet = "";
    for($i = 0; $i < 6; $i++)
       $packet .= chr(0xFF);
    for($j = 0; $j < 16; $j++)
    {
      for($k = 0; $k < 6; $k++)
      {
        $str = substr($mac, $k * 2, 2);
        $dec = hexdec($str);
        $packet .= chr($dec);
      }
    }
    $ret = fwrite($nic, $packet);
    fclose($nic);
    if ($ret)
    {
      echo "";
      return true;
    }
  }
  echo "ERROR";
  return false;
}  
?>';
        return $Script;
    }

    protected function Decode($Method, $KodiPayload)
    {
        switch ($Method)
        {
            case 'GetProperties':
                foreach ($KodiPayload as $param => $value)
                {
                    IPS_SetHidden($this->GetIDForIdent(substr($param, 3)), !$value);
                }
                break;
            case 'Power':
                if ($KodiPayload['Value'])
                    $this->SetValueBoolean('Power', true);
                else
                    $this->SetValueBoolean('Power', false);

                break;
        }
    }

################## ActionHandler

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident)
        {
            case "Power":
                $this->Power($Value);
                break;
            case "shutdown":
            case "reboot":
            case "hibernate":
            case "suspend":
            case "ejectOpticalDrive":
                $this->{ucfirst($Ident)}();
                break;
            default:
                trigger_error('Invalid Ident.', E_USER_NOTICE);
                break;
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

    public function Power(boolean $Value)
    {
        if (!is_bool($Value))
        {
            trigger_error('Value must be boolean', E_USER_NOTICE);
            return false;
        }

        if ($Value)
        {
            return $this->WakeUp();
        } else
        {
            switch ($this->ReadPropertyInteger('PowerOff'))
            {
                case 0:
                    return $this->Shutdown();
                case 1:
                    return $this->Hibernate();
                case 2:
                    return $this->Suspend();
            }
        }
    }

    public function WakeUp()
    {
        $ID = $this->ReadPropertyInteger('PowerScript');
        if ($ID > 0)
        {
            if (IPS_RunScriptWaitEx($ID, array("SENDER" => "Kodi.System")) == "")
            {
                $this->SetValueBoolean('Power', true);
                return true;
            }
            trigger_error('Invalid Ident.', E_USER_NOTICE);
        } else
            trigger_error('Invalid PowerScript for power on.', E_USER_NOTICE);
        return false;
    }

    public function Shutdown()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace, 'Shutdown');
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === 'OK')
        {
            $this->SetValueBoolean('Power', true);
            return true;
        }
        return false;
    }

    public function Hibernate()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace, 'Hibernate');
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === 'OK')
        {
            $this->SetValueBoolean('Power', true);
            return true;
        }
        return false;
    }

    public function Suspend()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace, 'Suspend');
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === 'OK')
        {
            $this->SetValueBoolean('Power', true);
            return true;
        }
        return false;
    }

    public function Reboot()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace, 'Reboot');
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        if ($ret === 'OK')
        {
            $this->SetValueBoolean('Power', true);
            return true;
        }
        return false;
    }

    public function EjectOpticalDrive()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace, 'EjectOpticalDrive');
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === 'OK';
    }

    public function RequestState(string $Ident)
    {
        return parent::RequestState($Ident);
    }

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