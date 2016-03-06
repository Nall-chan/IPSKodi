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
 * KodiDeviceGUI Klasse für den Namespace GUI der KODI-API.
 * Erweitert KodiBase.
 *
 */
class KodiDeviceGUI extends KodiBase
{

    /**
     * RPC-Namespace
     * 
     * @access private
     *  @var string
     * @value 'Application'
     */
    static $Namespace = 'GUI';

    /**
     * Alle Properties des RPC-Namespace
     * 
     * @access private
     *  @var array 
     */
    static $Properties = array(
        "currentwindow",
        "currentcontrol",
        "skin",
        "fullscreen",
        "stereoscopicmode"
    );

    /**
     * Interne Funktion des SDK.
     *
     * @access public
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyBoolean("showCurrentWindow", true);
        $this->RegisterPropertyBoolean("showCurrentControl", true);
        $this->RegisterPropertyBoolean("showSkin", true);
        $this->RegisterPropertyBoolean("showFullscreen", true);
        $this->RegisterPropertyBoolean("showScreensaver", true);
    }

    /**
     * Interne Funktion des SDK.
     * 
     * @access public
     */
    public function ApplyChanges()
    {

        if ($this->ReadPropertyBoolean('showCurrentWindow'))
        {
            $this->RegisterVariableString("currentwindow", "Aktuelles Fenster", "", 0);
            $this->RegisterVariableInteger("_currentwindowid", "Aktuelles Fenster (id)", "", 0);
            IPS_SetHidden($this->GetIDForIdent('_currentwindowid'), true);
        }
        else
        {
            $this->UnregisterVariable("currentwindow");
            $this->UnregisterVariable("_currentwindowid");
        }

        if ($this->ReadPropertyBoolean('showCurrentControl'))
            $this->RegisterVariableString("currentcontrol", "Aktuelles Control", "", 1);
        else
            $this->UnregisterVariable("currentcontrol");

        if ($this->ReadPropertyBoolean('showSkin'))
        {
            $this->RegisterVariableString("skin", "Aktuelles Skin", "", 2);
            $this->RegisterVariableString("_skinid", "Aktuelles Skin (id)", "", 2);
            IPS_SetHidden($this->GetIDForIdent('_skinid'), true);
        }
        else
        {
            $this->UnregisterVariable("skin");
            $this->UnregisterVariable("_skinid");
        }

        if ($this->ReadPropertyBoolean('showFullscreen'))
        {
            $this->RegisterVariableBoolean("fullscreen", "Vollbild", "~Switch", 3);
            $this->EnableAction("fullscreen");
        }
        else
            $this->UnregisterVariable("fullscreen");

        if ($this->ReadPropertyBoolean('showScreensaver'))
            $this->RegisterVariableBoolean("screensaver", "Bildschirmschoner", "~Switch", 4);
        else
            $this->UnregisterVariable("screensaver");

        parent::ApplyChanges();
    }

################## PRIVATE     
    /**
     * Dekodiert die empfangenen Events und Anworten auf 'GetProperties'.
     * 
     * @access protected
     * @param string $Method RPC-Funktion ohne Namespace
     * @param object $KodiPayload Der zu dekodierende Datensatz als Objekt.
     */

    protected function Decode($Method, $KodiPayload)
    {
        switch ($Method)
        {
            case 'GetProperties':
                foreach ($KodiPayload as $param => $value)
                {
                    switch ($param)
                    {
                        case "currentcontrol":
                            $this->SetValueString("currentcontrol", $value->label);
                            break;
                        case "currentwindow":
                            $this->SetValueString("currentwindow", $value->label);
                            $this->SetValueInteger("_currentwindowid", $value->id);
                            break;
                        case "fullscreen":
                            $this->SetValueBoolean("fullscreen", $value);
                            break;
                        case "skin":
                            $this->SetValueString("skin", $value->name);
                            $this->SetValueString("_skinid", $value->id);
                            break;
                    }
                }
                break;
            case 'OnScreensaverDeactivated':
                $this->SetValueBoolean("screensaver", false);
                break;
            case 'OnScreensaverActivated':
                $this->SetValueBoolean("screensaver", true);
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
            case "fullscreen":
                if ($this->SetFullscreen($Value) === false)
                trigger_error('Error set fullscreen.', E_USER_NOTICE);
                break;
            default:
                trigger_error('Invalid Ident.', E_USER_NOTICE);
                break;
        }
    }

################## PUBLIC
    /**
     * IPS-Instanz-Funktion 'KODIGUI_SetFullscreen'.
     * De-/Aktiviert den Vollbildmodus.
     *
     * @access public
     * @param boolean $Value True für Vollbild aktiv, False bei inaktiv.
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */

    public function SetFullscreen(boolean $Value)
    {
        if (!is_bool($Value))
        {
            trigger_error('Value must be boolean', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->SetFullscreen(array("fullscreen" => $Value));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        $this->SetValueBoolean("fullscreen", $ret);
        return $ret === $Value;
    }

    /**
     * IPS-Instanz-Funktion 'KODIGUI_ShowNotification'.
     * Erzeugt eine Benachrichtigung
     *
     * @access public
     * @param string $Title
     * @param string $Message
     * @param string $Image
     * @param integer $Timeout
     * @return boolean true bei erfolgreicher Ausführung, sonst false.
     */
    public function ShowNotification(string $Title, string $Message, string $Image, integer $Timeout)
    {
        if (!is_string($Title))
        {
            trigger_error('Title must be string', E_USER_NOTICE);
            return false;
        }
        if (!is_string($Message))
        {
            trigger_error('Message must be string', E_USER_NOTICE);
            return false;
        }
        if (!is_int($Timeout))
        {
            trigger_error('Timeout must be integer', E_USER_NOTICE);
            return false;
        }

        $Data = array("title" => $Title, "message" => $Message);

        if (is_string($Image))
            $Data['image'] = $Image;
        if ($Timeout <> 0)
            $Data['timeout'] = $Timeout;

        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->ShowNotification($Data);
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === $Title;
    }

    /**
     * IPS-Instanz-Funktion 'KODIGUI_ActivateWindow'.
     * Aktiviert ein Fenster
     *
     * @access public
     * @param string $Window Das zu aktivierende Fenster
     * @return boolean true bei Erfolg, sonst false.
     */
    public function ActivateWindow(string $Window)
    {
        if (!is_string($Window))
        {
            trigger_error('Window must be string', E_USER_NOTICE);
            return false;
        }
        $KodiData = new Kodi_RPC_Data(self::$Namespace);
        $KodiData->ActivateWindow(array('window' => $Window));
        $ret = $this->Send($KodiData);
        if (is_null($ret))
            return false;
        return $ret === $Window;
    }

    /**
     * IPS-Instanz-Funktion 'KODIGUI_RequestState'. Frage eine oder mehrere Properties ab.
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