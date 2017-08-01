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
 * Base generator
 */
abstract class Generator
{

    /**
     * Generates parser
     * @return string
     */
    public function __toString()
    {
        return $this->generate();
    }

    /**
     * Writes generated output to file
     * @param string|resource
     * @return int|bool bytes written, FALSE on failure
     */
    public function writeToFile($file)
    {
        if (is_string($file)) {
            return file_put_contents($file, $this->generate());
        }
        else if (is_resource($file) && get_resource_type($file) === 'file') {
            return fwrite($file, $this->generate());
        }

        throw new BadMethodCallException('Argument file must be a filename or opened file handle.');
    }

    /**
     * Generates parser code
     * @return string generated code
     */
    abstract protected function generate();
}
