Milestone 1
===========
-	Enumeration support
-	Test directives
-	Output/result redesign
	-	validate: partial resolve (results only)
		-	separate callbacks resolution from result resolution
-	Builder: inherit, results, etc...
-	Simple parser demo
-	Examples
-	README

Milestone 2
===========
-	Facade syntax
-	ABNF parser
-	Choice: shortest/longest by sorting? (stable sort!)
-	Ungreedy repeater
	-	Parent responsible for ungreedy backtracking?
-	Own Exception class
-	Traits for stuff
-	Match; handle child_matches differently? Fluent (not in constructor)
-	Callbacks
	-	Match; error/warnings/etc (flexible decorators w/ callbacks)
		-	Define decorators at runtime
		-	Also impacts Match properties and methods
	-	Match results as arrays (optional through name.sub or name. or name[]?)
	-	Cast parser-tree to string
-	Test
	-	Directive
	-	Core
	-	Abstract
	-	Scanner
	-	resultAs
	-	assignTo
	-	callback

New directive/context architecture
->nocase->
->case->
->lexeme->
->scanner()->
->choice(asdasdf, self::FIRST)

Phase 2
=======
-	Name; Comprehend/Comprehend
-	Exception; ConstructionException, ParseException, CallbackException
-	Non-greedy/greedy repeat.
-	Anchor-optional parsing.
-	Predefineds (global)
-	Add token-handling.
-	Build an AST using callbacks.
-	Use traits where possible and reasonable.
-	Facade (extract before/after/etc first!)