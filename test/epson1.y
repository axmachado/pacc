grammar Epson;

option (
    eol="\n"
    indentation="    "
    algorithm="LR"
)

@inner {
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

@currentToken {
    $tokenValue = isset($this->tokens[0]) ? $this->tokens[0] : null;
    echo "processing $tokenValue \n";
    return $tokenValue;
}

@currentTokenLexeme {
    $this->_currentToken();
}

@currentTokenType {
    $tokenType = isset($this->types[0]) ? $this->types[0] : null;
    echo "type: $tokenType\n";
    return $tokenType;
}

@nextTokenType {
    return isset($this->types[1]) ? $this->types[1] : 0;
}

@nextToken {
    $this->tokens = substr($this->tokens, 1);
    $this->types = array_slice ($this->types, 1);
}

@footer {
    $parser = new Epson;
    $result = $parser->run();
    var_dump($result);
}

target
    : listaDicionarios
    ;

listaDicionarios
    : INICIO dicionario dicionarios_adicionais { $2[] = $1; $$ = $2; }
    ;

dicionarios_adicionais
    : FIM { $$ = array(); }
    | dicionario dicionarios_adicionais { $$ = $1; }
    ;
    
dicionario
    : INICIO item continua { $2[] = $1; $$ = $2; }
    ;
    
item
    : ID DI LETTER { $$ = $1 . ':' . $2; } 
    ;
    
continua
    : FIM { $$ = array(); }
    | IS item continua { $2[] = $1; $$ = $2; }
    ;