<?php

namespace HtaccessCapabilityTester\Testers\Helpers;

use \HtaccessCapabilityTester\HttpResponse;
use \HtaccessCapabilityTester\TestResult;
use \HtaccessCapabilityTester\Testers\AbstractTester;

/**
 * Class for interpreting responses using a defined interpretation table.
 *
 * @package    HtaccessCapabilityTester
 * @author     Bjørn Rosell <it@rosell.dk>
 * @since      Class available since 0.7
 */
class ResponseInterpreter
{

    /**
     * Parse status string (failure | success | inconclusive) to bool|null.
     *
     * @param  string  $statusString  (failure | success | inconclusive)
     * @return bool|null
     */
    private static function parseStatusString($statusString)
    {
        $status = null;
        switch ($statusString) {
            case 'failure':
                $status = false;
                break;
            case 'inconclusive':
                $status = null;
                break;
            case 'success':
                $status = true;
                break;
        }
        return $status;
    }

    /**
     * Evaluate condition (string examination)
     *
     * @param  string  $val
     * @param  string  $operator  (is-empty | equals | not-equals | begins-with)
     * @param  string  $arg1  (only required for some operators)
     * @return bool
     */
    private static function evaluateConditionForString($operator, $val, $arg1)
    {
        switch ($operator) {
            case 'is-empty':
                return ($val == '');
            case 'equals':
                return ($val == $arg1);
            case 'not-equals':
                return ($val != $arg1);
            case 'begins-with':
                return (strpos($val, $arg1) === 0);
        }
        return false;
    }

    /**
     * Evaluate condition  (hash examination)
     *
     * @param  array  $val
     * @param  string $operator  (is-empty | equals | not-equals | begins-with)
     * @param  string $arg1  (only required for some operators)
     * @return bool
     */
    private static function evaluateConditionForHash($operator, $val, $arg1, $arg2)
    {
        switch ($operator) {
            case 'contains-key':
                return (isset($val[$arg1]));
            case 'not-contains-key':
                return (!isset($val[$arg1]));
            case 'contains-key-value':
                return (isset($val[$arg1]) && ($val[$arg1] == $arg2));
            case 'not-contains-key-value':
                return (!isset($val[$arg1]) || ($val[$arg1] != $arg2));
        }
        return false;
    }

    /**
     * Interpret line.
     *
     * @param HttpResponse    $response
     * @param array           $line
     *
     * @return  TestResult|null  If the condition matches, a TestResult is returned, otherwise null
     */
    private static function interpretLine($response, $line)
    {
        // ie:
        // ['inconclusive', 'body', 'is-empty'],
        // ['failure', 'statusCode', 'equals', '500']
        // ['success', 'headers', 'contains-key-value', 'X-Response-Header-Test', 'test'],

        $status = self::parseStatusString($line[0]);

        if (!isset($line[1])) {
            return new TestResult($status, '');
        }

        $propertyToExamine = $line[1];
        $operator = $line[2];
        $arg1 = (isset($line[3]) ? $line[3] : '');
        $arg2 = (isset($line[4]) ? $line[4] : '');

        $valString = '';
        $valHash = [];
        $valType = '';
        switch ($propertyToExamine) {
            case 'status-code':
                $valString = $response->statusCode;
                $valType = 'string';
                break;
            case 'body':
                $valString = $response->body;
                $valType = 'string';
                break;
            case 'headers':
                $valHash = $response->getHeadersHash();
                $valType = 'hash';
                break;
        }

        $reason = $propertyToExamine . ' ' . $operator;
        if (isset($line[3])) {
            $reason .= ' "' . implode('" "', array_slice($line, 3)) . '"';
        }
        if (($propertyToExamine == 'status-code') && ($operator == 'not-equals')) {
            $reason .= ' - it was: ' . $valString;
        }
        $result = new TestResult($status, $reason);

        $match = false;
        if ($valType == 'string') {
            $match = self::evaluateConditionForString($operator, $valString, $arg1);
        } elseif ($valType == 'hash') {
            $match =  self::evaluateConditionForHash($operator, $valHash, $arg1, $arg2);
        }
        if ($match) {
            return $result;
        }
        return null;
    }

    /**
     * Interpret a response using an interpretation table.
     *
     * @param HttpResponse    $response
     * @param array           $interpretationTable
     *
     * @return TestResult   If there is no match, the test result will have status = false and
     *                      info = "no-match".
     */
    public static function interpret($response, $interpretationTable)
    {
        foreach ($interpretationTable as $i => $line) {
            $testResult = self::interpretLine($response, $line);
            if (!is_null($testResult)) {
                return $testResult;
            }
        }
        return new TestResult(null, 'no-match');
    }
}
