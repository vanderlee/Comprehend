Simple PHP Object-Oriented Parser framework
============================================
v0.1

Copyright &copy; 2011-2013 Martijn W. van der Lee (http://martijn.vanderlee.com)
MIT (http://www.opensource.org/licenses/mit-license.php)

Make full LALR(1) lexers/tokenizers/parsers with ease.

Features
--------
-	Closely follows BNF syntax using objects as operands.
-	Whitespace skipping.
-	Token system.
-	Optional case (in)sensitivity.

Example
-------
### BNF
-	word	:= [A-Za-z]+
-	list	:= (word {',' word}*)?
### PHPOOParser code (using P helper class):
-	$word	= P::plus(P::alpha());
-	$list	= P::optional(P::seq($word, P::kleene(P::seq(',', $word))));

Files
-----
-	Parser.php		contains all classes.
-	P.php			contains a helper class for syntactic sugar.
-	UnitTest.php	simplistic unittesting framework.
-	text.php 		Full regression test of everything in Parser.php and P.php

State
-----
-	Mostly complete and usable.
-	Token system not fully implemented.
-	Some room for improvement in both features and performance.
-	Only ISO-8895-1 support, no UTF-8 or fancier yet.
-	No support for streaming/converting input yet.
