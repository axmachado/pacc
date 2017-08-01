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
 * Represents grammar
 */
class Grammar
{
    /**
     * Grammar name
     * @var string
     */
    public $name;

    /**
     * Options
     * @var array
     */
    public $options = array();

    /**
     * @var Set <PaccNonterminal>
     */
    public $nonterminals;

    /**
     * @var Set <PaccTerminal>
     */
    public $terminals;

    /**
     * @var Set <PaccProduction>
     */
    public $productions;

    /**
     * @var Nonterminal
     */
    public $start;

    /**
     * Initializes grammar G = (N, T, P, S)
     * @param Set <PaccNonterminal>
     * @param Set <PaccTerminal>
     * @param Set <PaccProduction>
     * @param Nonterminal
     */
    public function __construct(Set $nonterminals, Set $terminals, Set $productions, Nonterminal $start)
    {
        // check
        if ($nonterminals->getType() !== Nonterminal::class) {
            throw new \InvalidArgumentException(
                'PaccSet<PaccNonterminal> expected, PaccSet<' . 
                $nonterminals->getType() . '> given.'
            );
        }

        if ($terminals->getType() !== Terminal::class) {
            throw new \InvalidArgumentException(
                'PaccSet<PaccTerminal> expected, PaccSet<' .
                $terminals->getType() . '> given.'
            );
        }

        if ($productions->getType() !== Production::class) {
            throw new \InvalidArgumentException(
                'PaccSet<PaccProduction> expected, PaccSet<' .
                $productions->getType() . '> given.'
            );
        }

        // initialize
        $this->nonterminals = $nonterminals;
        $this->terminals = $terminals;
        $this->productions = $productions;
        $this->start = $start;
    }
}
