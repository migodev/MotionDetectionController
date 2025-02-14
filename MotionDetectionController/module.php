<?php

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class MotionDetectionController extends IPSModule {
    
    use HelperSwitchDevice;
    use HelperDimDevice;
    
    public function Create() {
        parent::Create();
        
        //Properties
        $this->RegisterPropertyInteger('MotionDetectorObject', 0);
        $this->RegisterPropertyString('PropertyCondition', '');
        $this->RegisterPropertyInteger('OffAction', 0);
        $this->RegisterPropertyString('OutputVariables', '[]');
        $this->RegisterPropertyInteger('DimBrightness', 0);      
       
        //Variables
        $ActiveOptions = json_encode([
            [
                'Value' => true,
                'Caption' => 'Automatik',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0x00ff00
            ],[
                'Value' => false,
                'Caption' => 'Deaktiviert',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0xff0000
            ]
        ]);    
        $this->RegisterVariableBoolean('Active', 'Bewegungsmelder Modus aktiv', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off', 'OPTIONS' => $ActiveOptions]);
        $this->EnableAction('Active');
        
        
        $this->RegisterVariableBoolean('Motion', 'Bewegungsmelder Zustand', ['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'ICON' => 'MOTION', "PROFILE" => "~Motion"]);
    }
    
    public function ApplyChanges() {
        parent::ApplyChanges();
        
        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }
        
        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        
        $inputTriggerOkCount = 0;
        if($this->ReadPropertyInteger("MotionDetectorObject") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("MotionDetectorObject"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("MotionDetectorObject"));
            $inputTriggerOkCount = 1;
        }
               
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];
            $this->RegisterReference($outputID);
        }
        
        //Check status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        $outputVariablesOkCount = 0;
        foreach ($outputVariables as $outputVariable) {
            if ($this->GetOutputStatus($outputVariable['VariableID']) == 'OK') {
                $outputVariablesOkCount++;
            }
        }
        
        //If we are missing triggers or outputs the instance will not work
        if (($inputTriggerOkCount == 0) || ($outputVariablesOkCount == 0)) {
            $status = IS_INACTIVE;
        } else {
            $status = IS_ACTIVE;
        }
        
        $this->SetStatus($status);
    }
    
    public function GetConfigurationForm() {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
                
        //Set status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $jsonForm['elements'][2]['values'][] = [
                'Status' => $this->GetOutputStatus($outputVariable['VariableID'])
            ];
        }

        return json_encode($jsonForm);
    }
    
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        //https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/messages/
        if ($Message == VM_UPDATE) {          
            $rawMotionData = $Data[0];
            $this->ValidateAndSetResult($rawMotionData);
        }
    }
    
    public function SetActive(bool $Value) {
        $this->SetValue('Active', $Value);
    }
    
    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                if (!$Value) {
                    switch ($this->ReadPropertyInteger('OffAction')) {
                        case 0: // keep the lights on
                            break;
                        case 1: // Switch Off immediately
                            $this->CheckAndSwitchLights(false);
                            break;
                    }
                } else {
                    // if enabled, check the motion status and set result
                    $MotionData = GetValueBoolean($this->ReadPropertyInteger("MotionDetectorObject"));
                    $this->ValidateAndSetResult($MotionData);
                }
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }
    
    private function ValidateAndSetResult($MotionData) {
        $varActive = $this->GetValue('Active');
        if ($varActive === true) {
            if ($MotionData === true) {
                $this->SetResult(true);
            } else {
                $this->SetResult(false);
            }
        } else if ($varActive === false) {
            $this->SetResult(false);
        }
    }
    
    private function SetResult (bool $Value) {
        $this->SetValue('Motion', $Value);
        
        $this->CheckAndSwitchLights($Value);
    }
    
    private function CheckAndSwitchLights($Value) {
        $conditionResult = IPS_IsConditionPassing($this->ReadPropertyString('PropertyCondition'));
        
        if ($conditionResult === true) {
            $this->SwitchVariable($Value);
        }
    }
    
    private function GetOutputStatus($outputID) {
        if (!IPS_VariableExists($outputID)) {
            return 'Missing';
        } else {
            switch (IPS_GetVariable($outputID)['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    return self::getSwitchCompatibility($outputID);
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    return self::getDimCompatibility($outputID);
                default:
                    return 'Bool/Int/Float required';
            }
        }
    }
    
    private function SwitchVariable(bool $Value) {
        
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];
            
            $doResend = false;
            
            //Depending on the type we need to switch differently
            switch (IPS_GetVariable($outputID)['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    if ($doResend || (self::getSwitchValue($outputID) != $Value)) {
                        self::switchDevice($outputID, $Value);
                    }
                    break;
                    
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    $dimDevice = function ($Value) use ($outputID, $doResend)
                    {
                        if ($doResend || (self::getDimValue($outputID) != $Value)) {
                            self::dimDevice($outputID, $Value);
                        }
                    };
                    
                    if ($Value) {
                        $brightness = $this->ReadPropertyInteger("DimBrightness");
                        if ($brightness === 0) {
                            $brightness = 100;
                        }
                        $dimDevice($brightness);
                    } else {
                        $dimDevice(0);
                    }
                    break;
                    
                default:
                    //Unsupported. Do nothing
            }
        }
    }
}
?>