Milestone 1
===========
-	not-Char parser
-	Debug logging mode
-	Array argument; choice or sequence?
	-	Or different per caller?
	-	Resolve definition/factory as late as possible
-	Recursion definition order test w/ ruleset
	-	Implement definition on parse
	-	Stub parser?
-	Builder: results, etc...
	-	If set, dump any "deeper" results and only use them for your own good.
-	Examples
-	Readme's
-	Test directive stacking
-	Space() directive; false/true support
-	Pure Match classes tests
-	Exceptions: runtime, designtime; inventorize!

Milestone 2
===========
-	Facade
	$C = new Comprehend;
	$C->barbaz = $C->text('baz')->or->text('bar');
	$C->fooba = $C->text('foo')->barbaz;
-	ABNF parser
-	Own Exception class
-	Traits for stuff
-	Match; handle child_matches differently? Fluent (not in constructor)
-	Callbacks
	-	Match; error/warnings/etc (flexible decorators w/ callbacks)
		-	Define decorators at runtime
		-	Also impacts Match properties and methods
	-	Match results as arrays (optional through name.sub or name. or name[]?)
	-	Cast parser-tree to string

Phase 2
=======
-	Exception; ConstructionException, ParseException, CallbackException
-	Non-greedy/greedy repeat.
-	Ungreedy repeater
	-	Parent responsible for ungreedy backtracking?
-	Anchor-optional parsing.
-	Predefineds (global)
-	Add token-handling.
-	Build an AST using callbacks.
