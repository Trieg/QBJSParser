<?php

namespace FL\QBJSParser\Serializer;

use FL\QBJSParser\Exception\Serializer\JsonDeserializerInvalidConditionException;
use FL\QBJSParser\Exception\Serializer\JsonDeserializerInvalidJsonException;
use FL\QBJSParser\Exception\Serializer\JsonDeserializerMissingConditionException;
use FL\QBJSParser\Exception\Serializer\JsonDeserializerRuleKeyException;
use FL\QBJSParser\Model\Rule;
use FL\QBJSParser\Model\RuleGroup;
use FL\QBJSParser\Model\RuleGroupInterface;
use FL\QBJSParser\Model\RuleInterface;

class JsonDeserializer implements DeserializerInterface
{
    /**
     * @inheritdoc
     */
    public function deserialize(string $string) : RuleGroupInterface
    {
        $decodedRuleGroup = json_decode($string, true);
        if(is_null($decodedRuleGroup) || !is_array($decodedRuleGroup)){
            throw new JsonDeserializerInvalidJsonException();
        }
        $deserializedRuleGroup = $this->deserializeRuleGroup($decodedRuleGroup);
        return $deserializedRuleGroup;
    }

    /**
     * @param array $decodedRuleGroup
     * @return RuleGroupInterface
     */
    private function deserializeRuleGroup(array $decodedRuleGroup) : RuleGroupInterface
    {
        if (!isset($decodedRuleGroup['condition'])) {
            throw new JsonDeserializerMissingConditionException('Missing condition in RuleGroup');
        }

        switch ($decodedRuleGroup['condition']) {
            case 'AND':
                $mode = RuleGroupInterface::MODE_AND;
                break;
            case 'OR':
                $mode = RuleGroupInterface::MODE_OR;
                break;
            default:
                throw new JsonDeserializerInvalidConditionException('Invalid condition ' . $decodedRuleGroup['condition'] . ' in RuleGroup');
                break;
        }

        $decodedRulesAndRuleGroups = $decodedRuleGroup['rules'];
        $decodedRuleGroups = [];
        $decodedRules = [];

        foreach ($decodedRulesAndRuleGroups as $ruleOrGroup) {
            if (isset($ruleOrGroup['condition'])) {
                $decodedRuleGroups[] = $ruleOrGroup;
            } elseif (isset($ruleOrGroup['id'])) {
                $decodedRules[] = $ruleOrGroup;
            }
        }

        $deserializedRuleGroup = new RuleGroup($mode);

        foreach ($decodedRuleGroups as $decodedRuleGroup) {
            $deserializedRuleGroup->addRuleGroup($this->deserializeRuleGroup($decodedRuleGroup));
        }
        foreach ($decodedRules as $decodedRule) {
            $deserializedRuleGroup->addRule($this->deserializeRule($decodedRule));
        }

        return $deserializedRuleGroup;
    }

    /**
     * @param array $decodedRule
     * @return RuleInterface
     * @throws \Exception
     */
    private function deserializeRule(array $decodedRule) : RuleInterface
    {
        $missingKey = (
            (!array_key_exists('id', $decodedRule)) ||
            (!array_key_exists('field', $decodedRule)) ||
            (!array_key_exists('type', $decodedRule)) ||
            (!array_key_exists('operator', $decodedRule)) ||
            (!array_key_exists('value', $decodedRule))
        );
        if ($missingKey) {
            $keysGiven = array_keys($decodedRule);
            $keysGiven = implode(", ", $keysGiven);
            throw new JsonDeserializerRuleKeyException('Keys Given: ' . $keysGiven . '. Expecting id, field, type, operator, value');
        }

        $id = $decodedRule['id'];
        $field = $decodedRule['field'];
        $type = $decodedRule['type'];
        $operator = $decodedRule['operator'];
        $value = $decodedRule['value'];

        if(!is_array($value)){
            $value = $this->convertValueAccordingToType($type, $value);
            return new Rule($id, $field, $type, $operator, $value);
        }
        else{
            $valuesArray = $value;
            foreach($valuesArray as $key => $value){
                $valuesArray[$key] = $this->convertValueAccordingToType($type, $value);
            }
            return new Rule($id, $field, $type, $operator, $valuesArray);
        }
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return mixed
     */
    private function convertValueAccordingToType(string $type, $value)
    {
        if(is_null($value) || $value === 'null' || $value === 'NULL'){
            return null; // nulls shouldn't be converted
        }
        switch ($type) { /** @see Rule::$type */
            case 'string':
                $value = strval($value);
                break;
            case 'integer':
                $value = intval($value);
                break;
            case 'double':
                $value = doubleval($value);
                break;
            case 'date':
                $value = new \DateTime($value);
                break;
            case 'time':
                $value = new \DateTime($value);
                break;
            case 'datetime':
                $value = new \DateTime($value);
                break;
            case 'boolean':
                $value = boolval($value);
                break;
        }
        return $value;
    }

}