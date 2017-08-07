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

use Pacc\Tokens\WhitespaceToken;
use Pacc\Tokens\IdToken;
use Pacc\Tokens\StringToken;
use Pacc\Tokens\SpecialToken;
use Pacc\Tokens\CommentToken;
use Pacc\Tokens\BadToken;
use Pacc\Tokens\EndToken;
use Pacc\Tokens\CodeToken;

/**
 * Converts string into stream of tokens
 */
class Lexer implements TokenStream
{

    /**
     * Mapping from token regexes to token classes
     * @var array
     */
    private static $map = array(
        '/^(\s+)/Ss'                                                 => WhitespaceToken::class,
        '/^([a-zA-Z][a-zA-Z_]*)/S'                                   => IdToken::class,
        '/^(\'(?:\\\'|[^\'])*\'|"(?:\\"|[^"])*"|`(?:\\`|[^`])*`)/SU' => StringToken::class,
        '/^(@|\\\\|\\.|=|\(|\)|:|\||\{|\}|;)/S'                      => SpecialToken::class,
        '/^(\/\*.*\*\/)/SUs'                                         => CommentToken::class,
        '/^(.)/Ss'                                                   => BadToken::class,
    );

    /**
     * String to tokenize
     * @var string
     */
    private $string = '';

    /**
     * Current token
     * @var Token
     */
    private $current = NULL;

    /**
     * Current line of string to tokenize
     * @var
     */
    private $line = 1;

    /**
     * Current position on current line of string to tokenize
     * @var int
     */
    private $position = 1;

    /**
     * Buffered tokens
     * @var array
     */
    private $buffer = array();

    /**
     * Initializes lexer
     * @param string string to tokenize
     * @param int
     */
    public function __construct($string = '', $start_line = 1)
    {
        $this->line   = $start_line;
        $this->string = $string;
    }

    /**
     * Get current token
     * @return Token
     */
    public function current()
    {
        if ($this->current === NULL) {
            $this->lex();
        }
        return $this->current;
    }

    /**
     * Synonynm for lex()
     * @return Token
     */
    public function next()
    {
        return $this->lex();
    }

    /**
     * Get next token
     * @return Token
     */
    public function lex()
    {
        if (!empty($this->buffer)) {
            return $this->current = array_shift($this->buffer);
        }
        if (empty($this->string)) {
            return $this->current = new EndToken(NULL, $this->line, $this->position);
        }

        foreach (self::$map as $regex => $class) {
            if (!preg_match($regex, $this->string, $m)) {
                continue;
            }

            $token = new $class($m[1], $this->line, $this->position);

            if ($token instanceof SpecialToken && $m[1] === '{') {
                $offset = 0;
                do {
                    if (($rbrace = strpos($this->string, '}', $offset)) === FALSE) {
                        array_push($this->buffer, new CodeToken($this->string, $this->line, $this->position + 1));
                        return $this->current = $token;
                    }

                    $offset = $rbrace + 1;
                    $code   = substr($this->string, 0, $rbrace + 1);
                    $test   = preg_replace($r      = '#"((?<!\\\\)\\\\"|[^"])*$
                                          |"((?<!\\\\)\\\\"|[^"])*"
                                          |\'((?<!\\\\)\\\\\'|[^\'])*\'
                                          |\'((?<!\\\\)\\\\\'|[^\'])*$
                                          #x', '', $code);
                } while (substr_count($test, '{') !== substr_count($test, '}'));

                $code = substr($code, 1, strlen($code) - 2);
                array_push($this->buffer, new CodeToken($code, $this->line, $this->position + 1));
                $m[1] .= $code;
            }

            break;
        }

        $lines      = substr_count($m[1], "\n") + substr_count($m[1], "\r\n") + substr_count($m[1], "\r");
        $this->line += $lines;

        if ($lines > 0) {
            $segments       = preg_split("/\r?\n|\r/", $m[1]);
            $this->position = strlen(end($segments)) + 1;
        }
        else {
            $this->position += strlen($m[1]);
        }

        $this->string = substr($this->string, strlen($m[1]));

        return $this->current = $token;
    }

    /**
     * Creates instance from string
     * @param string
     * @param int
     * @return self
     */
    public static function fromString($string, $start_line = 1)
    {
        return new self($string, $start_line);
    }

    /**
     * Creates instance from file
     * @param string
     * @return self
     */
    public static function fromFile($filename)
    {
        return self::fromString(file_get_contents($filename));
    }

}
