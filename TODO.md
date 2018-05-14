Phase 1
=======
-	Refactor names
-	Rename stuff like 'Match'
-	Make separate match/mismatch (implementing match interface or abstract)
-	Unittest ALL
-	isTerminal (inherit)
-	isTerminalSequence (sequence/repeat of only isTerminal()s
-	AST/Token tree from results
-	Match; handle child_matches differently
-	Match; error/warnings/etc (flexible decorators w/ callbacks)
	-	Define decorators at runtime
	-	Also impacts Match properties and methods
-	Match results as arrays (optional through name.sub or name. or name[]?)
-	Special parser Exception
-	Cast parser-tree to string

Phase 2
=======
-	Anchor-optional parsing.
-	Predefineds (global)
-	Add token-handling.
-	Build an AST using callbacks.
-	Use traits where possible and reasonable.
-	Facade (extract before/after/etc first!)