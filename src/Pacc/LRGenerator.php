<?php

/*
 *  pacc - PHP yACC
 *  Parser Generator for PHP
 * 
 * Copyright (c) 2009-2010 Jakub Kulham (jakubkulhan@gmail.com)
 *               2017 Alexandre Machado (axmachado@gmail.com)
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

namespace Pacc;

/**
 * Generates LR parser
 */
class LRGenerator extends Generator
{

    /**
     * @var Grammar
     */
    private $grammar;

    /**
     * Max symbol index (for table pitch)
     * @var int
     */
    private $table_pitch;

    /**
     * @var Set[]  <PaccLRItem>
     */
    private $states;

    /**
     * @var LRJump[]
     */
    private $jumps;

    /**
     * @var int[]
     */
    private $table = array();

    /**
     * @var string
     */
    private $generated;

    /**
     * Header of generated file
     * @var string
     */
    private $header;

    /**
     * Code to put inside the generated class
     * @var string
     */
    private $inner;

    /**
     * Footer of generated file
     * @var string
     */
    private $footer;

    /**
     * One indentation level
     * @var string
     */
    private $indentation = '    ';

    /**
     * End of line
     * @var string
     */
    private $eol = PHP_EOL;

    /**
     * Prefix for terminals
     * @var string
     */
    private $terminals_prefix = 'self::';

    /**
     * Name of parse method
     * @var string
     */
    private $parse = 'doParse';

    /**
     * Initializes generator
     * @param Grammar
     */
    public function __construct(Grammar $grammar)
    {
        $this->grammar = $grammar;

        // order sensitive actions!
        file_put_contents('php://stderr', 'augment... ');
        $this->augment();
        file_put_contents('php://stderr', 'indexes... ');
        $this->computeIndexes();
        file_put_contents('php://stderr', 'first... ');
        $this->computeFirst();
        file_put_contents('php://stderr', 'follow... ');
        $this->computeFollow();
        file_put_contents('php://stderr', 'states... ');
        $this->computeStates();
        file_put_contents('php://stderr', 'table... ');
        $this->computeTable();
        file_put_contents('php://stderr', "\n");

        foreach (array('header', 'inner', 'footer', 'indentation', 'eol', 'terminals_prefix', 'parse') as $name) {
            if (isset($grammar->options[$name])) {
                $this->$name = $grammar->options[$name];
            }
        }
    }

    /**
     * Generate parser
     * @return string
     */
    protected function generate()
    {
        if ($this->generated === NULL) {
            $this->doGenerate();
        }

        return $this->generated;
    }

    /**
     * Really generates parser
     * @return string
     */
    private function doGenerate()
    {
        // header
        $this->generated .= '<?php' . $this->eol;

        if (strpos($this->grammar->name, '\\') === FALSE) {
            $classname = $this->grammar->name;
        }
        else {
            $namespace       = explode('\\', $this->grammar->name);
            $classname       = array_pop($namespace);
            $this->generated .= 'namespace ' . implode('\\', $namespace) . ';' . $this->eol;
        }

        $this->generated .= $this->header . $this->eol;
        $this->generated .= 'class ' . $classname . $this->eol . '{' . $this->eol;

        // parser
        $table = array();
        foreach ($this->table as $k => $v) {
            if ($v === NULL) {
                continue;
            }
            $table[] = $k . '=>' . $v;
        }
        $this->generated .= $this->indentation . 'private $_table = array(' . implode(',', $table) . ');' . $this->eol;
        $this->generated .= $this->indentation . 'private $_table_pitch = ' . $this->table_pitch . ';' . $this->eol;

        $terminals_types  = array();
        $terminals_values = array();
        foreach ($this->grammar->terminals as $terminal) {
            if ($terminal->type !== NULL) {
                $terminals_types[] = $this->terminals_prefix . $terminal->type . '=>' . $terminal->index;
            }
            else if ($terminal->value !== NULL) {
                $terminals_values[] = var_export($terminal->value, TRUE) . '=>' . $terminal->index;
            }
        }
        $this->generated .= $this->indentation . 'private $_terminals_types = array(' . implode(',', $terminals_types) . ');' . $this->eol;
        $this->generated .= $this->indentation . 'private $_terminals_values = array(' . implode(',', $terminals_values) . ');' . $this->eol;

        $productions_lengths = array();
        $productions_lefts   = array();
        foreach ($this->grammar->productions as $production) {
            $productions_lengths[] = $production->index . '=>' . count($production->right);
            $productions_lefts[]   = $production->index . '=>' . $production->left->index;

            $this->generated .= $this->indentation . 'private function _reduce' . $production->index . '() {' . $this->eol;
            $this->generated .= $this->indentation . $this->indentation . 'extract(func_get_arg(0), EXTR_PREFIX_INVALID, \'_\');' . $this->eol;
            $this->generated .= $this->indentation . $this->indentation . $this->phpizeVariables('$$ = NULL;') . $this->eol;

            if ($production->code !== NULL) {
                $this->generated .= $this->indentation . $this->indentation . $this->phpizeVariables($production->code) . $this->eol;
            }
            else {
                $this->generated .= $this->indentation . $this->indentation . $this->phpizeVariables('$$ = $1;') . $this->eol;
            }

            $this->generated .= $this->indentation . $this->indentation . $this->phpizeVariables('return $$;') . $this->eol;
            $this->generated .= $this->indentation . '}' . $this->eol;
        }
        $this->generated .= $this->indentation . 'private $_productions_lengths = array(' . implode(',',
                                                                                                    $productions_lengths) . ');' . $this->eol;
        $this->generated .= $this->indentation . 'private $_productions_lefts = array(' . implode(',',
                                                                                                  $productions_lefts) . ');' . $this->eol;

        $this->generated .= <<<E
    private function {$this->parse}() {
        \$stack = array(NULL, 0);
        while(true) {
            \$state = end(\$stack);
            \$terminal = 0;
            if (isset(\$this->_terminals_types[\$this->_currentTokenType()])) {
                \$terminal = \$this->_terminals_types[\$this->_currentTokenType()];
            } else if (isset(\$this->_terminals_values[\$this->_currentTokenLexeme()])) {
                \$terminal = \$this->_terminals_values[\$this->_currentTokenLexeme()];
            }

            if (!isset(\$this->_table[\$state * \$this->_table_pitch + \$terminal])) {
                throw new \Exception('Illegal action.');
            }

            \$action = \$this->_table[\$state * \$this->_table_pitch + \$terminal];

            if (\$action === 0) { // => accept
                array_pop(\$stack); // go away, state!
                return array_pop(\$stack);

            } 
            else if (\$action > 0) { // => shift
                array_push(\$stack, \$this->_currentToken());
                array_push(\$stack, \$action);
                \$this->_nextToken();

            } 
            else { // \$action < 0 => reduce
                \$popped = array_splice(\$stack, count(\$stack) - (\$this->_productions_lengths[-\$action] * 2));
                \$args = array();
                if (\$this->_productions_lengths[-\$action] > 0) { 
                    foreach (range(0, (\$this->_productions_lengths[-\$action] - 1) * 2, 2) as \$i) {
                        \$args[\$i / 2 + 1] = \$popped[\$i];
                    }
                }

                \$goto = \$this->_table[end(\$stack) * \$this->_table_pitch + \$this->_productions_lefts[-\$action]];

                \$reduce = '_reduce' . (-\$action);
                if (method_exists(\$this, \$reduce)) {
                    array_push(\$stack, \$this->\$reduce(\$args));
                } 
                else {
                    array_push(\$stack, NULL);
                }

                array_push(\$stack, \$goto);
    
            }
    
        }
    }


E;


        // footer
        foreach (array('currentToken', 'currentTokenType', 'currentTokenLexeme', 'nextToken') as $method) {
            if (isset($this->grammar->options[$method])) {
                $this->generated .= $this->indentation . 'private function _' . $method . '() {' . $this->eol;
                $this->generated .= $this->grammar->options[$method] . $this->eol;
                $this->generated .= $this->indentation . '}' . $this->eol . $this->eol;
            }
        }

        $this->generated .= $this->inner . $this->eol;
        $this->generated .= '}' . $this->eol;
        $this->generated .= $this->footer;
    }

    /**
     * Converts special variables to PHP variables
     * @param string
     * @return string
     */
    protected function phpizeVariables($s)
    {
        return str_replace('$$', '$__0', preg_replace('~\$(\d+)~', '$__$1', $s));
    }

    /**
     * Adds new start nonterminal and end terminal
     * @return void
     */
    private function augment()
    {
        $newStart                       = new Nonterminal('$start');
        $this->grammar->startProduction = new Production($newStart, array($this->grammar->start), NULL);
        $this->grammar->productions->add($this->grammar->startProduction);
        $this->grammar->nonterminals->add($newStart);
        $this->grammar->start           = $newStart;

        $this->grammar->epsilon        = new Terminal('$epsilon');
        $this->grammar->epsilon->index = -1;

        $this->grammar->end        = new Terminal('$end');
        $this->grammar->end->index = 0;
        $this->grammar->end->first = new Set('integer');
        $this->grammar->end->first->add($this->grammar->end->index);
    }

    /**
     * Compute grammar symbols and productions indexes
     * @return void
     */
    private function computeIndexes()
    {
        $i = 1;
        foreach ($this->grammar->terminals as $terminal) {
            $terminal->index = $i++;
            $terminal->first = new Set('integer');
            $terminal->first->add($terminal->index);
        }
        $this->grammar->terminals->add($this->grammar->end);

        $this->max_terminal = $i - 1;

        foreach ($this->grammar->nonterminals as $nonterminal) {
            $nonterminal->first  = new Set('integer');
            $nonterminal->follow = new Set('integer');
            $nonterminal->index  = $i++;
        }

        $this->table_pitch = $i - 1;

        $i = 1;
        foreach ($this->grammar->productions as $production) {
            $production->index = $i++;
        }
    }

    /**
     * @return void
     */
    private function computeFirst()
    {
        foreach ($this->grammar->productions as $production) {
            if (count($production->right) === 0) {
                $production->left->first->add($this->grammar->epsilon->index);
            }
        }

        do {
            $done = TRUE;
            foreach ($this->grammar->productions as $production) {
                foreach ($production->right as $symbol) {
                    foreach ($symbol->first as $index) {
                        if ($index !== $this->grammar->epsilon->index &&
                                !$production->left->first->contains($index)) {
                            $production->left->first->add($index);
                            $done = FALSE;
                        }
                    }

                    if (!$symbol->first->contains($this->grammar->epsilon->index)) {
                        break;
                    }
                }
            }
        } while (!$done);
    }

    /**
     * @return void
     */
    private function computeFollow()
    {
        $this->grammar->start->follow->add($this->grammar->end->index);

        foreach ($this->grammar->productions as $production) {
            for ($i = 0, $len = count($production->right) - 1; $i < $len; ++$i) {
                if ($production->right[$i] instanceof Terminal) {
                    continue;
                }
                foreach ($production->right[$i + 1]->first as $index) {
                    if ($index === $this->grammar->epsilon->index) {
                        continue;
                    }
                    $production->right[$i]->follow->add($index);
                }
            }
        }

        do {
            $done = TRUE;
            foreach ($this->grammar->productions as $production) {
                for ($i = 0, $len = count($production->right); $i < $len; ++$i) {
                    if ($production->right[$i] instanceof Terminal) {
                        continue;
                    }

                    $empty_after = TRUE;
                    for ($j = $i + 1; $j < $len; ++$j) {
                        if (!$production->right[$j]->first->contains($this->grammar->epsilon->index)) {
                            $empty_after = FALSE;
                            break;
                        }
                    }

                    if ($empty_after && !$production->right[$i]->follow->contains($production->left->follow)) {
                        $production->right[$i]->follow->add($production->left->follow);
                        $done = FALSE;
                    }
                }
            }
        } while (!$done);
    }

    /**
     * @return void
     */
    private function computeStates()
    {
        $items        = new Set(LRItem::class);
        $items->add(new LRItem($this->grammar->startProduction, 0, $this->grammar->end->index));
        $this->states = array($this->closure($items));
        $symbols      = new Set(Symbol::class);
        $symbols->add($this->grammar->nonterminals);
        $symbols->add($this->grammar->terminals);

        for ($i = 0; $i < count($this->states); ++$i) { // intentionally count() in second clause
            foreach ($symbols as $symbol) {
                $jump = $this->jump($this->states[$i], $symbol);
                if ($jump->isEmpty()) {
                    continue;
                }
                $already_in = FALSE;
                $toState    = 0;
                foreach ($this->states as $state) {
                    if ($state->__eq($jump)) {
                        $already_in = TRUE;
                        $jump       = $state;
                        break;
                    }
                    $toState++;
                }

                if (!$already_in) {
                    $this->states[] = $jump;
                    $toState        = count($this->states) - 1;
                }

                $this->jumps[] = new LRJump($this->states[$i], $symbol, $jump);
            }
        }
    }

    /**
     * @return void
     */
    private function computeTable()
    {
        for ($state = 0, $len = count($this->states); $state < $len; ++$state) {
            $items = $this->states[$state];

            // shifts
            foreach ($this->grammar->terminals as $terminal) {
                $do_shift = FALSE;

                foreach ($items as $item) {
                    $afterDot = $item->afterDot();
                    if (reset($afterDot) !== FALSE &&
                            reset($afterDot)->__eq($terminal)) {
                        $do_shift = TRUE;
                        break;
                    }
                }

                if ($do_shift) {
                    $tableIndex               = $state * $this->table_pitch + $terminal->index;
                    $nextState                = $this->getNextState($items, $terminal);
                    $this->table[$tableIndex] = $nextState;
                }
            }

            // reduces/accepts
            foreach ($items as $item) {
                if (count($item->afterDot()) > 0) {
                    continue;
                }
                $tableIndex = $state * $this->table_pitch + $item->terminalindex;

                if ($item->production->__eq($this->grammar->startProduction)) { // accept
                    $this->table[$tableIndex] = 0;
                }
                else {
                    if (isset($this->table[$tableIndex])) {
                        if ($this->table[$tableIndex] > 0) {
                            throw new \Exception('Shift-reduce conflict.');
                        }
                        else if ($this->table[$tableIndex] < 0) {
                            throw new \Exception('Reduce-reduce conflict: ' . $item);
                        }
                        else {
                            throw new \Exception('Accpet-reduce conflict: ' . $item);
                        }
                    }
                    $this->table[$tableIndex] = -$item->production->index;
                }
            }

            // gotos
            foreach ($this->grammar->nonterminals as $nonterminal) {
                $stateToGo  = $this->getNextState($items, $nonterminal);
                $tableIndex = $state * $this->table_pitch + $nonterminal->index;
                if ($stateToGo != null) {
                    $this->table[$tableIndex] = $stateToGo;
                }
            }
        }
    }

    /**
     * @return int
     */
    private function getNextState(Set $items, Symbol $symbol)
    {
        if ($items->getType() !== LRItem::class) {
            throw new \InvalidArgumentException(
            'Bad type - expected PaccSet<LRItem>, given PaccSet<' .
            $items->getType() . '>.'
            );
        }

        foreach ($this->jumps as $jump) {
            if ($jump->from->__eq($items) && $jump->symbol->__eq($symbol)) {
                for ($i = 0, $len = count($this->states); $i < $len; ++$i) {
                    if ($jump->to->__eq($this->states[$i])) {
                        return $i;
                    }
                }
            }
        }

        return NULL;
    }

    /**
     * @return Set <PaccLRItem>
     */
    private function closure(Set $items)
    {
        if ($items->getType() !== LRItem::class) {
            throw new \InvalidArgumentException(
            'Bad type - expected PaccSet<LRItem>, given PaccSet<' .
            $items->getType() . '>.'
            );
        }

        do {
            $done = TRUE;

            $itemscopy = clone $items;

            foreach ($items as $item) {
                $afterDot = $item->afterDot();
                if (!(count($item->afterDot()) >= 1 &&
                        reset($afterDot) instanceof Nonterminal)) {
                    continue;
                }

                $newitems   = new Set(LRItem::class);
                $beta_first = new Set('integer');
                if (count($afterDot) > 1) {
                    reset($afterDot);
                    $beta_first->add(next($afterDot)->first);
                    $beta_first->delete($this->grammar->epsilon->index);
                }

                if ($beta_first->isEmpty()) {
                    $beta_first->add($item->terminalindex);
                }
                $B = reset($afterDot);

                foreach ($this->grammar->productions as $production) {
                    if ($B->__eq($production->left)) {
                        foreach ($beta_first as $terminalindex) {
                            $newItem = new LRItem($production, 0, $terminalindex);
                            $newitems->add($newItem);
                        }
                    }
                }

                if (!$newitems->isEmpty() && !$itemscopy->contains($newitems)) {
                    $itemscopy->add($newitems);
                    $done = FALSE;
                }
            }

            $items = $itemscopy;
        } while (!$done);

        return $items;
    }

    /**
     * @param Set <PaccLRItem>
     * @param Symbol
     * @return Set <PaccLRItem>
     */
    private function jump(Set $items, Symbol $symbol)
    {
        if ($items->getType() !== LRItem::class) {
            throw new \InvalidArgumentException('Bad type - expected PaccSet<LRItem>, given PaccSet<' . $items->getType() . '>.');
        }

        $ret = new Set(LRItem::class);

        foreach ($items as $item) {
            $afterDot        = $item->afterDot();
            $symbolToProcess = reset($afterDot);
            if ($symbolToProcess != null) {
                if (!$symbolToProcess->__eq($symbol)) {
                    $symbolToProcess = null;
                }
            }
            if ($symbolToProcess != null) {
                $ret->add(new LRItem($item->production, $item->dot + 1, $item->terminalindex));
            }
        }

        return $this->closure($ret);
    }

}
