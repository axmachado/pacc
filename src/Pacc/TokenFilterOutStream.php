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
 * Filteres some tokens from stream
 */
class TokenFilterOutStream implements TokenStream
{

    /**
     * Stream
     * @var PaccTokenStream
     */
    private $stream;

    /**
     * Filters out there tokens
     * @var array
     */
    private $out;

    /**
     * Initializes filter stream
     * @param PaccTokenStreamable stream to be filtered
     * @param array tokens we do not want
     */
    public function __construct(TokenStream $stream, $out = NULL)
    {
        $this->stream = $stream;
        if (!is_array($out)) {
            $out = func_get_args();
            array_shift($out);
        }
        $this->out = array_flip($out);
    }

    /**
     * Get current token
     * @retrun PaccToken
     */
    public function current()
    {
        return $this->stream->current();
    }

    /**
     * Get next token
     * @return Token
     */
    public function next()
    {
        do {
            $token = $this->stream->next();
        } while (!($token instanceof Tokens\EndToken) && isset($this->out[get_class($token)]));
        return $token;
    }

}
