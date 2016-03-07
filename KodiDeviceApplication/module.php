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
 * KodiDeviceApplication Klasse für den Namespace Application der KODI-API.
 * Erweitert KodiBase.
 *
 */
class KodiDeviceApplication extends KodiBase
{

    /**
     * RPC-Namespace
     * 
     * @access private
     *  @var string
     * @value 'Application'
     */
    static $Namespace = 'Application';

    /**
     * Alle Properties des RPC-Namespace
     * 
     * @access private
     *  @var array 
     */
    static $Properties = array(
        "volume",
        "muted",
        "name",
        "version"
    );

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyBoolean("showName", true);
        $this->RegisterPropertyBoolean("showVersion", true);
        $this->RegisterPropertyBoolean("showExit", true);
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

        if ($this->ReadPropertyBoolean('showName'))
            $this->RegisterVariableString("name", "Name", "", 0);
        else
            $this->UnregisterVariable("name");

        if ($this->ReadPropertyBoolean('showVersion'))
            $this->RegisterVariableString("version", "Version", "", 1);
        else
            $this->UnregisterVariable("version");

        if ($this->ReadPropertyBoolean('showExit'))
        {
            $this->RegisterVariableInteger("quit", "Kodi beenden", "Action.Kodi", 2);
            $this->EnableAction("quit");
        }
        else
            $this->UnregisterVariable("quit");

        $this->RegisterVariableBoolean("mute", "Mute", "~Switch", 3);
        $this->EnableAction("mute");

        $this->RegisterVariableInteger("volume", "Volume", "~Intensity.100", 4);
        $this->EnableAction("volume");

        parent::ApplyChanges();
    }

################## PRIVATE     

    /**
     * Dekodiert die empfangenen Events und Anworten auf 'GetProperties'.
     *
     * @param string $Method RPC-Funktion ohne Namespace
     * @param object $KodiPayload Der zu dekodierende Datensatz als Objekt.
     */
    protected function Decode($Method, $KodiPayload)
    {
        foreach ($KodiPayload as $param => $value)
        {
            switch ($param)
            {
                case "mute":
                case "muted":
                    $this->SetValueBoolean("mute", $value);
                    break;
                case "volume":
                    $this->SetValueInteger("volume", $value);
                    break;
                case "name":
                    $this->SetValueString("name", $value);
                    break;
                case "version":
                    $this->SetValueString("version", $value->major . '.' . $value->minor);
                    break;
            }
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
            case "mute":
                if ($this->SetMute($Value) === false)
                    trigger_error('Error set mute', E_USER_NOTICE);

                break;
            case "volume":
                if ($this->SetVolume($Value) === false)
                    trigger_error('Error set volume', E_USER_NOTICE);

                break;
            case "quit":
                if ($this->Quit() === false)
                    trigger_error('Error exit Kodi', E_USER_NOTICE);

                break;
            default:
                trigger_error('Invalid Ident.', E_USER_NOTICE);
        }
    }

################## PUBLIC

    /**
     * IPS-Instanz-Funktion 'KODIAPP_SetMute'. De-/Aktiviert die Stummschaltung
     *
     * @access public
     * @param boolean $Value True für Stummschaltung aktiv, False bei inaktiv.
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function SetMute(boolean $Value)
    {
        if (!is_bool($Value))
        {
            trigger_error('Value must be boolean', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->SetMute(array("mute" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        $this->SetValueBoolean("mute", $ret);
        return $ret === $Value;
    }

    /**
     * IPS-Instanz-Funktion 'KODIAPP_SetVolume'. Setzen der Lautstärke
     *
     * @access public
     * @param integer $Value Neue Lautstärke
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function SetVolume(integer $Value)
    {
        if (!is_int($Value))
        {
            trigger_error('Value must be integer', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->SetVolume(array("volume" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        $this->SetValueInteger("volume", $ret);
        return $ret === $Value;
    }

    /**
     * IPS-Instanz-Funktion 'KODIAPP_Quit'. Beendet die Kodi-Anwendung
     *
     * @access public
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function Quit()
    {
        $KodiData = new Kodi_RPC_Data(self::$Namespace); //, 'Quit');
        $KodiData->Quit(null);
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return true;
    }

    /**
     * IPS-Instanz-Funktion 'KODIAPP_RequestState'. Frage eine oder mehrere Properties ab.
     *
     * @access public
     * @param string $Ident Enthält den Names des "properties" welches angefordert werden soll.
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function RequestState(string $Ident)
    {
        return parent::RequestState($Ident);
    }

}

/** @} */
?>