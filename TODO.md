#Todo
##Milestone 1 
 -  Extract Parser::parseCharacter
 -  Tests
     -  prefer context
     -  case sensitive context
     -  match classes
 -  Spacer: Required w/ optionality optional	        
 -  Builder: results, etc...
	 -	If set, dump any "deeper" results and only use them for your own good.
 -	Examples
     - Practical example
 -	Readme's
 -  Ruleset constructor with array of defines
 -  Extra "type" terminal parsers
     -  digit, alpha, hex, integer, float, etc...

##Milestone 2
-	Debug logging mode
-   Callback for result value; late processing
-   AST generation
     -  `->tree($token, $value = null)`
     -  Allow callback for value
-   Tokenizer
     -  `->token($token)`
-	Facade
     -  $C = new Comprehend;
	 -  $C->barbaz = $C->text('baz')->or->text('bar');
	 -  $C->fooba = $C->text('foo')->barbaz;	
	 -  Special version of ruleset?
-	ABNF parser
-	Match; handle child_matches differently? Fluent (not in constructor)
-	Callbacks
	 -	Match; error/warnings/etc (flexible decorators w/ callbacks)
		 -	Define decorators at runtime
		 -	Also impacts Match properties and methods
	 -	Match results as arrays (optional through name.sub or name. or name[]?)
	 -	Cast parser-tree to string

Phase 2
=======
-   Stream parsing
     -  Low memory profile
     -  Sourcing from stream (rewind, position-cache, buffer-cache)
     -  `->stream(callable $callback, $token = null)`    
-	Non-greedy/greedy repeat.
-	Ungreedy repeater
	 -	Parent responsible for ungreedy backtracking?
-	Anchor-optional parsing.
-	Predefineds (global)
-	Add token-handling.
-	Build an AST using callbacks.
