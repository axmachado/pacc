<?php

/*
 *  pacc - PHP yACC
 *  Parser Generator for PHP
 * 
 * Copyright (c) 2017 Alexandre Machado (axmachado@gmail.com)
 * 
 * The MIT license
 * 
 *     Permission is hereby granted, free of charge, to any person
 *     obtaining a copy of this software and associated documentation
 *     files (the "Software"), to deal in the Software without
 *     restriction, including without limitation the rights to use,
 *     copy, modify, merge, publish, distribute, sublicense, and/or sell
 *     copies of the Software, and to permit persons to whom the
 *     Software is furnished to do so, subject to the following
 *     conditions:
 * 
 *     The above copyright notice and this permission notice shall be
 *     included in all copies or substantial portions of the Software.
 * 
 *     THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 *     EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 *     OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 *     NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 *     HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 *     WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 *     FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 *     OTHER DEALINGS IN THE SOFTWARE.
 * 
 */

namespace Pacc\Runtime;

/**
 * Description of LRParserBase
 *
 * @author Alexandre Machado <alexandre@softnex.com.br>
 */
abstract class LRParserBase
{

    /**
     * @return array the parser table
     */
    protected abstract function &_getTable();

    /**
     * @return int the table pitch
     */
    protected abstract function _getTablePitch();

    /**
     * @return array the terminal types
     */
    protected abstract function &_getTerminalTypes();

    /**
     * @return array the terminal values
     */
    protected abstract function &_getTerminalValues();

    /**
     * @return array of production lengths
     */
    protected abstract function &_getProductionLengths();

    /**
     * @return array of production lefts
     */
    protected abstract function &_getProductionLefts();

    /**
     * @return array terminal symbol names for error messages.
     */
    protected abstract function &_getTerminalNames();

    /**
     * This function shoud be overriten by the lexer.
     * @return int the current token type from the lexer
     */
    protected abstract function _currentTokenType();

    /**
     * This function shoud be overriten by the lexer.
     * @return string the current token lexeme
     */
    protected function _currentTokenLexeme()
    {
        return $this->_currentToken();
    }

    /**
     * @return string the lexeme of the current token
     */
    protected abstract function _currentToken();

    /**
     * consume the current token and go to the next.
     */
    protected abstract function _nextToken();

    /**
     * return the contents of the table on a given position
     * @param int $position
     * @return int
     */
    protected function _getTableEntry($position)
    {
        $table = $this->_getTable();
        return $table[$position];
    }

    protected function _getTerminalsForState($state)
    {
        $terminals = $this->_getTerminalIdsForState($state);
        $expectedTerminals = array();
        foreach ($terminals as $terminal) {
            $expectedTerminals[] = $this->_getTerminalNames()[$terminal];
        }
        return $expectedTerminals;
    }

    protected function _getAction($state, $terminal)
    {
        $position = $state * $this->_getTablePitch() + $terminal;
        $table    = $this->_getTable();
        if (!isset($table[$position])) {
            $expectedTerminals = $this->_getTerminalsForState($state);
            $msg = "Invalid symbol. Expected: " . implode(" or ", $expectedTerminals)
                    . " but found '" . $this->_getTerminalName($terminal) . "'.";
            throw new \Exception($msg);
        }
        return $table[$position];
    }

    protected function _currentTerminal()
    {
        $types              = $this->_getTerminalTypes();
        $values             = $this->_getTerminalValues();
        $currentTokenType   = $this->_currentTokenType();
        $currentTokenLexeme = $this->_currentTokenLexeme();
        $terminal           = 0;
        if (isset($types[$currentTokenType])) {
            $terminal = $types[$currentTokenType];
        }
        else if (isset($values[$currentTokenLexeme])) {
            $terminal = $values[$currentTokenLexeme];
        }
        return $terminal;
    }

    protected function _getProductionLength($action)
    {
        if ($action < 0) {
            $action = -$action;
        }
        $lengths = $this->_getProductionLengths();
        return $lengths[$action];
    }

    protected function _getProductionLeft($action)
    {
        if ($action < 0) {
            $action = -$action;
        }
        $lefts = $this->_getProductionLefts();
        return $lefts[$action];
    }

    /**
     * Execute the "reduction" of a production.
     * @param int $action number of the production (-parser action)
     * @param array $args arguments.
     * @return mixed 
     */
    protected abstract function _reduce($action, $args);

    protected function _getTerminalName($terminalIndex)
    {
        $names = $this->_getTerminalNames();
        if (isset($names[$terminalIndex])) {
            return $names[$terminalIndex];
        }
        else {
            if ($terminalIndex == 0) {
                return "EOF";
            }
            else {
                return false;
            }
        }
    }

    protected function _doParse()
    {
        $stack = array(NULL, 0);

        while (true) {
            $state    = end($stack);
            $terminal = $this->_currentTerminal();

            $action = $this->_getAction($state, $terminal);

            if ($action === 0) {
                // accept
                array_pop($stack); // throw the state away
                return array_pop($stack);
            }
            else if ($action > 0) {
                // shift
                array_push($stack, $this->_currentToken());
                array_push($stack, $action);
                $this->_nextToken();
            }
            else {
                // $action < 0 -- reduce
                $productionLength = $this->_getProductionLength($action);
                // pop the arguments to the reduce method.
                $popped           = array_splice($stack, count($stack) - ($productionLength * 2));
                $args             = array();
                // put the arguments into the $args array
                if ($productionLength > 0) {
                    foreach (range(0, ($productionLength - 1) * 2, 2) as $i) {
                        $args[$i / 2 + 1] = $popped[$i];
                    }
                }
                $goto = $this->_getAction(end($stack), $this->_getProductionLeft($action));

                array_push($stack, $this->_reduce($action, $args));

                array_push($stack, $goto);
            }
        }
    }

}
