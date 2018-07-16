Milestone 1
===========
-	Builder: inherit, results, etc...
-	Examples
-	Readme's
-	Test directive stacking
-	Space() directive; false/true support
-	Pure Match classes tests

Milestone 2
===========
-	Facade
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
