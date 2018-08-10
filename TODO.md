#Todo
##Milestone 1
 -  RFC
     -  Implement
     -  Test
         -  What kind of coverage? All rules? Start rules
 -  Ruleset; on define, add a token/result rule.
 -  Library: configurable path?
 -  Optimize s-c-s arrays; pull-up sequence/choice of one
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
 -  Extra "type" terminal parsers (don't cost anything to include and not use)
     -  digit, alpha, hex, integer, float, etc...
 -  Match() must match entire length? Boolean option!

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
     -  To PHP code (design-time)
     -  To parser (run-time)
-	Callbacks
	 -	Match; error/warnings/etc (flexible decorators w/ callbacks)
		 -	Define decorators at runtime
		 -	Also impacts Match properties and methods
	 -	Match results as arrays (optional through name.sub or name. or name[]?)
 -  Ruleset
     -  Multiple levels in config (dot-path?), i.e. rfc1234.ws

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
