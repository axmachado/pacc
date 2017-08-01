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

use Pacc\Tokens\CodeToken;
use Pacc\Tokens\EndToken;
use Pacc\Tokens\IdToken;
use Pacc\Tokens\SpecialToken;
use Pacc\Tokens\StringToken;
use Pacc\Exceptions\UnexpectedToken;

/**
 * Fills grammar from token stream
 */
class Parser
{

    /**
     * Token stream
     * @var PaccTokenStream
     */
    private $stream;

    /**
     * @var Grammar
     */
    private $grammar;

    /**
     * @var string
     */
    private $grammar_name;

    /**
     * @var array
     */
    private $grammar_options = array();

    /**
     * @var Set <PaccNonterminal>
     */
    private $nonterminals;

    /**
     * @var Set <PaccTerminal>
     */
    private $terminals;

    /**
     * @var Set <PaccProduction>
     */
    private $productions;

    /**
     * Start symbol
     * @var Nonterminal
     */
    private $start;

    /**
     * Initializes instance
     * @param PaccTokenStream
     */
    public function __construct(TokenStream $stream)
    {
        $this->stream       = $stream;
        $this->terminals    = new Set(Terminal::class);
        $this->nonterminals = new Set(Nonterminal::class);
        $this->productions  = new Set(Production::class);
    }

    /**
     * Parse
     * @return Grammar
     */
    public function parse()
    {
        if ($this->grammar === NULL) {
            for (;;) {
                if ($this->stream->current() instanceof IdToken &&
                        $this->stream->current()->value === 'grammar') {
                    $this->stream->next();
                    $this->grammar_name = $this->backslashSeparatedName();
                }
                else if ($this->stream->current() instanceof IdToken &&
                        $this->stream->current()->value === 'option') {
                    $this->stream->next();
                    $this->options();
                }
                else if ($this->stream->current() instanceof SpecialToken &&
                        $this->stream->current()->value === '@') {
                    $this->stream->next();
                    $name                         = $this->periodSeparatedName();
                    $this->grammar_options[$name] = $this->code();
                }
                else {
                    break;
                }

                // optional semicolon
                if ($this->stream->current() instanceof SpecialToken &&
                        $this->stream->current()->value === ';') {
                    $this->stream->next();
                }
            }

            $this->rules();

            $this->grammar          = new Grammar($this->nonterminals, $this->terminals, $this->productions,
                                                      $this->start);
            $this->grammar->name    = $this->grammar_name;
            $this->grammar->options = $this->grammar_options;
        }

        return $this->grammar;
    }

    /**
     * @return string
     */
    private function backslashSeparatedName()
    {
        return $this->separatedName('\\');
    }

    /**
     * @return string
     */
    private function periodSeparatedName()
    {
        return $this->separatedName('.');
    }

    /**
     * @return string
     */
    private function separatedName($separator)
    {
        $name = '';
        $prev = NULL;

        $check = function ($current, $prev) use ($separator) {
            $result = ($current instanceof SpecialToken && $current->value === $separator) || $current instanceof IdToken;
            $result = $result && !($prev === NULL && $current->value === $separator);
            $result = $result && ($prev === NULL || get_class($current) !== get_class($prev));
            return $result;
        };

        while ($check($this->stream->current(), $prev)) {
            $name .= $this->stream->current()->value;
            $prev = $this->stream->current();
            $this->stream->next();
        }

        if (!($prev instanceof IdToken)) {
            throw new UnexpectedToken($this->stream->current());
        }

        return $name;
    }

    /**
     * @return string
     */
    private function code()
    {
        if (!($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '{')) {
            throw new UnexpectedToken($this->stream->current());
        }
        $this->stream->next();

        if (!($this->stream->current() instanceof CodeToken)) {
            throw new UnexpectedToken($this->stream->current());
        }
        $code = $this->stream->current()->value;
        $this->stream->next();

        if (!($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '}')) {
            throw new UnexpectedToken($this->stream->current());
        }
        $this->stream->next();

        return $code;
    }

    /**
     * @return void
     */
    private function options()
    {
        if (!($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '(')) {
            return $this->singleOption();
        }
        $this->stream->next();

        for (;;) {
            $this->singleOption();
            if ($this->stream->current() instanceof SpecialToken) {
                if ($this->stream->current()->value === ')') {
                    $this->stream->next();
                    break;
                }
                else if ($this->stream->current()->value === ';') {
                    $this->stream->next();
                    if ($this->stream->current() instanceof SpecialToken &&
                            $this->stream->current()->value === ')') {
                        $this->stream->next();
                        break;
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    private function singleOption()
    {
        $name  = $this->periodSeparatedName();
        $value = NULL;

        if (!($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '=')) {
            throw new UnexpectedToken($this->stream->current());
        }
        $this->stream->next();

        if ($this->stream->current() instanceof StringToken) {
            $value = $this->stream->current()->value;
            $this->stream->next();
        }
        else if ($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '{') {
            $value = $this->code();
        }
        else {
            throw new UnexpectedToken($this->stream->current());
        }

        $this->grammar_options[$name] = $value;
    }

    /**
     * @return void
     */
    private function rules()
    {
        do {
            if (!($this->stream->current() instanceof IdToken)) {
                throw new UnexpectedToken($this->stream->current());
            }

            $name  = new Nonterminal($this->stream->current()->value);
            if (($found = $this->nonterminals->find($name)) !== NULL) {
                $name = $found;
            }
            else {
                $this->nonterminals->add($name);
            }
            $this->stream->next();

            if ($this->start === NULL) {
                $this->start = $name;
            }

            if (!($this->stream->current() instanceof SpecialToken &&
                    $this->stream->current()->value === ':')) {
                throw new UnexpectedToken($this->stream->current());
            }
            $this->stream->next();

            do {
                list($terms, $code) = $this->expression();
                $production = new Production($name, $terms, $code);
                if (($found      = $this->productions->find($production)) === NULL) {
                    $this->productions->add($production);
                }
            } while ($this->stream->current() instanceof SpecialToken &&
            $this->stream->current()->value === '|' &&
            !($this->stream->next() instanceof EndToken));

            if (!($this->stream->current() instanceof SpecialToken &&
                    $this->stream->current()->value === ';')) {
                throw new UnexpectedToken($this->stream->current());
            }
            $this->stream->next();
        } while (!($this->stream->current() instanceof EndToken));
    }

    /**
     * @return array
     */
    private function expression()
    {
        $terms = $this->terms();

        $code = NULL;
        if ($this->stream->current() instanceof SpecialToken &&
                $this->stream->current()->value === '{') {
            $code = $this->code();
        }

        return array($terms, $code);
    }

    /**
     * @return array
     */
    private function terms()
    {
        $terms = array();

        while (($this->stream->current() instanceof IdToken ||
        $this->stream->current() instanceof StringToken)) {
            $t = $this->stream->current();
            $this->stream->next();

            if ($t instanceof IdToken) {
                if (ord($t->value[0]) >= 65 /* A */ && ord($t->value[0]) <= 90 /* Z */) { // terminal
                    $term  = new Terminal($t->value, $t->value, NULL);
                    if (($found = $this->terminals->find($term)) !== NULL) {
                        $term = $found;
                    }
                    else {
                        $this->terminals->add($term);
                    }
                }
                else { // nonterminal
                    $term  = new Nonterminal($t->value);
                    if (($found = $this->nonterminals->find($term)) !== NULL) {
                        $term = $found;
                    }
                    else {
                        $this->nonterminals->add($term);
                    }
                }
            }
            else {
                assert($t instanceof StringToken);
                $term  = new Terminal($t->value, NULL, $t->value);
                if (($found = $this->terminals->find($term)) !== NULL) {
                    $term = $found;
                }
                else {
                    $this->terminals->add($term);
                }
            }

            $terms[] = $term;
        }

        return $terms;
    }

}
