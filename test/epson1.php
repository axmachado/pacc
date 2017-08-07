<?php

class Epson
{
    private $_table = array(1=>3,7=>1,8=>2,13=>0,26=>-1,40=>5,48=>4,53=>5,54=>8,61=>6,62=>7,68=>10,76=>9,79=>5,80=>8,87=>6,88=>11,91=>-2,104=>-3,119=>13,123=>14,129=>12,134=>15,143=>-4,158=>-5,157=>-5,171=>-7,170=>-7,185=>10,193=>16,200=>17,210=>13,214=>14,220=>18,223=>-6,227=>-6,236=>-8,235=>-8);
    private $_table_pitch = 13;
    private $_terminals_types = array(self::INICIO=>1,self::FIM=>2,self::ID=>3,self::DI=>4,self::LETTER=>5,self::IS=>6);
    private $_terminals_values = array();
    private function _reduce1() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
        $__0 = $__1;
        return $__0;
    }
    private function _reduce2() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__2[] = $__1; $__0 = $__2; 
        return $__0;
    }
    private function _reduce3() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__0 = array(); 
        return $__0;
    }
    private function _reduce4() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__0 = $__1; 
        return $__0;
    }
    private function _reduce5() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__2[] = $__1; $__0 = $__2; 
        return $__0;
    }
    private function _reduce6() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__0 = $__1 . ':' . $__2; 
        return $__0;
    }
    private function _reduce7() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__0 = array(); 
        return $__0;
    }
    private function _reduce8() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
         $__2[] = $__1; $__0 = $__2; 
        return $__0;
    }
    private function _reduce9() {
        extract(func_get_arg(0), EXTR_PREFIX_INVALID, '_');
        $__0 = NULL;
        $__0 = $__1;
        return $__0;
    }
    private $_productions_lengths = array(1=>1,2=>3,3=>1,4=>2,5=>3,6=>3,7=>1,8=>3,9=>1);
    private $_productions_lefts = array(1=>7,2=>8,3=>10,4=>10,5=>9,6=>11,7=>12,8=>12,9=>13);
    private function doParse() {
        $stack = array(NULL, 0);
        while(true) {
            $state = end($stack);
            $terminal = 0;
            if (isset($this->_terminals_types[$this->_currentTokenType()])) {
                $terminal = $this->_terminals_types[$this->_currentTokenType()];
            } else if (isset($this->_terminals_values[$this->_currentTokenLexeme()])) {
                $terminal = $this->_terminals_values[$this->_currentTokenLexeme()];
            }

            if (!isset($this->_table[$state * $this->_table_pitch + $terminal])) {
                throw new \Exception('Illegal action.');
            }

            $action = $this->_table[$state * $this->_table_pitch + $terminal];

            if ($action === 0) { // => accept
                array_pop($stack); // go away, state!
                return array_pop($stack);

            } 
            else if ($action > 0) { // => shift
                array_push($stack, $this->_currentToken());
                array_push($stack, $action);
                $this->_nextToken();

            } 
            else { // $action < 0 => reduce
                $popped = array_splice($stack, count($stack) - ($this->_productions_lengths[-$action] * 2));
                $args = array();
                if ($this->_productions_lengths[-$action] > 0) { 
                    foreach (range(0, ($this->_productions_lengths[-$action] - 1) * 2, 2) as $i) {
                        $args[$i / 2 + 1] = $popped[$i];
                    }
                }

                $goto = $this->_table[end($stack) * $this->_table_pitch + $this->_productions_lefts[-$action]];

                $reduce = '_reduce' . (-$action);
                if (method_exists($this, $reduce)) {
                    array_push($stack, $this->$reduce($args));
                } 
                else {
                    array_push($stack, NULL);
                }

                array_push($stack, $goto);
    
            }
    
        }
    }

    private function _currentToken() {

    $tokenValue = isset($this->tokens[0]) ? $this->tokens[0] : null;
    echo "processing $tokenValue \n";
    return $tokenValue;

    }

    private function _currentTokenType() {

    $tokenType = isset($this->types[0]) ? $this->types[0] : null;
    echo "type: $tokenType\n";
    return $tokenType;

    }

    private function _currentTokenLexeme() {

    $this->_currentToken();

    }

    private function _nextToken() {

    $this->tokens = substr($this->tokens, 1);
    $this->types = array_slice ($this->types, 1);

    }


    const LETTER=1;
    const ID=2;
    const INICIO=3;
    const DI=4;
    const IS=5;
    const FIM=6;

    
    private $tokens;
    
    public function run() {
        $this->tokens = '{x:a,y:b}';
        $this->types  = array ( self::INICIO, 
                                self::ID, self::DI, self::LETTER,
                                self::IS, 
                                self::ID, self::DI, self::LETTER,
                                self::FIM );
        
        return $this->doParse();
    }    

}

    $parser = new Epson;
    $result = $parser->run();
    var_dump($result);
