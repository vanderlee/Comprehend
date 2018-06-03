Phase 1
=======
-	Refactor
	-	Rename stuff like 'Match' to Result
	-	Make separate match/mismatch (implementing match interface or abstract)
	-	isTerminal (inherit)
	-	isTerminalSequence (sequence/repeat of only isTerminal()s
	-	Exceptions
	-	Group sequence/repeat (scannables)
-	How to provide scanner to parser without a Context?
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