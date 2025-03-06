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
        $this->RegisterPropertyInteger('FalseActionIfConditionDosntMatch', 0);
        $this->RegisterPropertyString('OutputVariables', '[]');
        $this->RegisterPropertyInteger('DimBrightness', 0);
        $this->RegisterPropertyBoolean('setMotionDataAfterEnablingController', false);
       
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
        
        $this->RegisterVariableBoolean('InputMotion', 'Eingangswert', ['PRESENTATION' => VARIABLE_PRESENTATION_LEGACY, 'ICON' => 'MOTION', "PROFILE" => "~Motion"]);
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
        // Keep status OK, also if there are no lights
        if (count($outputVariables) == 0) {
            $outputVariablesOkCount = 1; 
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
            $getProfileName = function ($variableID)
            {
                $variable = IPS_GetVariable($variableID);
                if ($variable['VariableCustomProfile'] != '') {
                    return $variable['VariableCustomProfile'];
                } else {
                    return $variable['VariableProfile'];
                }
            };
            
            $isProfileReversed = function ($VariableID) use ($getProfileName)
            {
                return preg_match('/\.Reversed$/', $getProfileName($VariableID));
            };
            
            if ($isProfileReversed($SenderID)) {
                //invert internal value
                $rawMotionData = !$Data[0];
            } else {
                $rawMotionData = $Data[0];
            }

            $this->SetInputValue($rawMotionData);
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
                            $this->ValidateAndSetResult(false);
                            break;
                    }
                    $this->SetResult(false);
                } else {
                    // if enabled, check the motion status and set result
                    if ($this->ReadPropertyBoolean("setMotionDataAfterEnablingController")) {
                        $MotionData = GetValueBoolean($this->ReadPropertyInteger("MotionDetectorObject"));
                        $this->ValidateAndSetResult($MotionData);
                    }
                }
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }
    
    private function SetInputValue(bool $Value) {
        $this->SetValue('InputMotion', $Value);
    }
    
    private function ValidateAndSetResult($MotionData) {
        $varActive = $this->GetValue('Active');
        $conditionResult = IPS_IsConditionPassing($this->ReadPropertyString('PropertyCondition'));
        
        if ($conditionResult === true) {
            if ($varActive === true) {
                $this->SetResult($MotionData);
                $this->SwitchLights($MotionData);
            } else if ($varActive === false) {
                $this->SetResult(false);
            }
        } else {
            //if condition result is false, do nothing, except we explicit want to execute false values (but only if mode is active
            // example: Condition checks brightness, true before Limit, light keeps on without limit, because false value were skipped due to not valid condition
            if (($this->ReadPropertyInteger('FalseActionIfConditionDosntMatch') === 1) && ($MotionData === false) && ($varActive === true)) {
                $this->SetResult($MotionData);
                $this->SwitchLights($MotionData);
            }
        }
    }
    
    private function SetResult (bool $Value) {
        $varMotion = $this->GetValue('Motion');
        if ($varMotion != $Value) {
            $this->SetValue('Motion', $Value);
        }
    }
    
    private function SwitchLights($Value) {
        $this->SwitchVariable($Value);

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