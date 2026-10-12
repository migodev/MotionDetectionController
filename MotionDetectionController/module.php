<?php

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class MotionDetectionController extends IPSModuleStrict {
    
    use HelperSwitchDevice;
    use HelperDimDevice;
    
    public function Create(): void {
        parent::Create();
        
        //Properties
        $this->RegisterPropertyInteger('MotionDetectorObject', 0);
        $this->RegisterPropertyString('PropertyCondition', '[]');
        $this->RegisterPropertyInteger('OffAction', 0);
        $this->RegisterPropertyInteger('FalseActionIfConditionDosntMatch', 0);
        $this->RegisterPropertyString('OutputVariables', '[]');
        $this->RegisterPropertyInteger('DimBrightness', 0);
        $this->RegisterPropertyBoolean('setMotionDataAfterEnablingController', false);
        $this->RegisterPropertyBoolean('enableDelayOffControl', false);
        $this->RegisterPropertyInteger('Duration', 0);
       
        //Timers
        $this->RegisterTimer('DelayOffTimer', 0, "MMDC_DelayOff(\$_IPS['TARGET']);");

        //Variables
        $ActiveOptions = json_encode([
            [
                'Value' => true,
                'Caption' => 'Automatik',
                'IconActive' => false,
                'IconValue' => '',
                'ColorActive' => true,
                'ColorValue' => 65280
            ],[
                'Value' => false,
                'Caption' => 'Deaktiviert',
                'IconActive' => false,
                'IconValue' => '',
                'ColorActive' => true,
                'ColorValue' => 16711680
            ]
        ]);    

        $MotionsOptions = json_encode([
            [
                'Value' => true,
                'Caption' => 'Bewegung erkannt',
                'IconActive' => false,
                'IconValue' => '',
                'ColorValue' => 255,
                'ColorActive' => true
            ],[
                'Value' => false,
                'Caption' => 'Keine Bewegung',
                'IconActive' => false,
                'IconValue' => '',
                'ColorActive' => false,
                'ColorValue' => ''
            ]
        ]); 
        $this->RegisterVariableBoolean('Active', 'Bewegungsmelder Modus aktiv', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off', 'OPTIONS' => $ActiveOptions]);
        $this->EnableAction('Active');
        
        $this->RegisterVariableBoolean('Motion', 'Bewegungsmelder Zustand', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'radar', 'OPTIONS' => $MotionsOptions]);
        
        $this->RegisterVariableBoolean('InputMotion', 'Eingangswert', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'radar', 'OPTIONS' => $MotionsOptions]);
    }
    
    public function ApplyChanges(): void {
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
        
        // Display only if Delay Off Control is enabled
        $this->MaintainVariable('Remaining', $this->Translate('Remaining Time'), VARIABLETYPE_INTEGER, ['PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'COUNTDOWN_TYPE' => 1], 10, $this->ReadPropertyBoolean('enableDelayOffControl'));

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
    
    public function GetConfigurationForm(): string {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
                
        //Set status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $jsonForm['elements'][2]['values'][] = [
                'Status' => $this->GetOutputStatus($outputVariable['VariableID'])
            ];
        }

        // Show Delay Off Control if activated
        $jsonForm['elements'][3]['expanded'] = $this->ReadPropertyBoolean('enableDelayOffControl');

        return json_encode($jsonForm);
    }
    
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void {
        //https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/messages/
        if ($Message == VM_UPDATE) {          
            if ($this->IsVariableProfileReversed($SenderID)) {
                //invert internal value
                $rawMotionData = !$Data[0];
            } else {
                $rawMotionData = $Data[0];
            }

            $this->SetInputValue($rawMotionData);
            $this->ValidateAndSetResult($rawMotionData);
        }
    }
    
    public function SetActive(bool $Value): void {
        $this->SetValue('Active', $Value);
    }
    
    public function RequestAction(string $Ident, mixed $Value): void {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                $this->followUpActionsAfterSetActive($Value);
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }
    
    public function followUpActionsAfterSetActive(bool $status): void {
        if (!$Value) {
            switch ($this->ReadPropertyInteger('OffAction')) {
                case 0: // keep the lights on
                    break;
                case 1: // Switch Off immediately
                    $this->SwitchLights(false);
                    $this->SendDebug('OffAction', "Switch Off immediately", 0);
                    break;
            }
            $this->SetResult(false);
        } else {
            // if enabled, check the motion status and set result
            if ($this->ReadPropertyBoolean("setMotionDataAfterEnablingController")) {
                $MotionData = GetValueBoolean($this->ReadPropertyInteger("MotionDetectorObject"));
                
                if ($this->IsVariableProfileReversed($this->ReadPropertyInteger("MotionDetectorObject"))) {
                    //invert internal value
                    $MotionData = !$MotionData;
                }
                $this->SendDebug('setMotionDataAfterEnablingController', "Motion Detector Input: ".json_encode($MotionData), 0);
                $this->ValidateAndSetResult($MotionData);
            }
        }
    }

    private function IsVariableProfileReversed(int $variableId): bool {
        $presentation = self::resolvePresentation($variableId);
        $this->SendDebug('IsVariableProfileReversed', json_encode($presentation['reversed']), 0);
        return $presentation['reversed'];
    }
    
    private function SetInputValue(bool $Value): void {
        $this->SetValue('InputMotion', $Value);
        $this->SendDebug('SetInputValue', json_encode($Value), 0);
    }
    
    private function ValidateAndSetResult(bool $MotionData): void {
        $varActive = $this->GetValue('Active');
        $conditionResult = IPS_IsConditionPassing($this->ReadPropertyString('PropertyCondition'));
        $this->SendDebug('validation', "conditionResult: ". json_encode($conditionResult), 0);

        if ($conditionResult === true) {
            if ($varActive === true) {
                if ($this->ReadPropertyBoolean("enableDelayOffControl") === true) {
                    //Delay Off Control    
                    if ($MotionData === true) {
                        $duration = $this->ReadPropertyInteger('Duration') * 60;
                        $this->SetTimerInterval('DelayOffTimer', $duration * 1000 );
                        $this->SendDebug('delayOff-control', "timer start: ". $duration ." seconds", 0);
                        $this->SetValue('Remaining', time() + $duration);
                        $this->SetResult($MotionData);
                        $this->SwitchLights($MotionData);
                    } else {
                        // Keep Lights & update only Status
                        $this->SetResult($MotionData);
                    }
                } else {
                    $this->SetResult($MotionData);
                    $this->SwitchLights($MotionData);
                }
            } else if ($varActive === false) {
                $this->SetResult(false);
            }
        } else {
            //if condition result is false, do nothing, except we explicit want to execute false values (but only if mode is active
            // example: Condition checks brightness, true before Limit, light keeps on without limit, because false value were skipped due to not valid condition
            if (($this->ReadPropertyInteger('FalseActionIfConditionDosntMatch') === 1) && ($MotionData === false) && ($varActive === true) && ($this->ReadPropertyBoolean("enableDelayOffControl") === false)) {
                $this->SendDebug('validation', "conditionResult: is false, but we want to execute false values (FalseActionIfConditionDosntMatch is true)", 0);
                $this->SetResult($MotionData);
                $this->SwitchLights($MotionData);
            }
        }
    }
    
    public function DelayOff(): void {
        $this->SetTimerInterval('DelayOffTimer', 0 );
        $this->SwitchLights(false);
        $this->SendDebug('delayOff-control', "Set to off", 0);
    }

    private function SetResult (bool $Value): void {
        $varMotion = $this->GetValue('Motion');
        if ($varMotion != $Value) {
            $this->SendDebug('set-result', "Set motion to ".json_encode($Value), 0);
            $this->SetValue('Motion', $Value);
        }
    }
    
    private function SwitchLights(bool $Value): void {
        $this->SwitchVariable($Value);

    }
    
    private function GetOutputStatus(int $outputID): string {
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
    
    private function SwitchVariable(bool $Value): void {
        
        $outputVariables = json_decode($this->ReadPropertyString('OutputVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $outputID = $outputVariable['VariableID'];
            
            $doResend = false;
            //Depending on the type we need to switch differently
            switch (IPS_GetVariable($outputID)['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    if ($doResend || (self::getSwitchValue($outputID) != $Value)) {
                        self::switchDevice($outputID, $Value);
                        $this->SendDebug('SwitchVariable', "Set switch to ".json_encode($Value), 0);
                    }
                    break;
                    
                case VARIABLETYPE_INTEGER:
                case VARIABLETYPE_FLOAT:
                    $dimDevice = function ($Value) use ($outputID, $doResend)
                    {
                        if ($doResend || (self::getDimValue($outputID) != $Value)) {                            
                            self::dimDevice($outputID, $Value);
                            $this->SendDebug('SwitchVariable', "Set dimmer to ".json_encode($Value), 0);
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