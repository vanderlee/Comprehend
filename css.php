<?php

	header('Content-Type: text/html; charset=utf-8'); 

	require_once(dirname(__FILE__).'/Parser.php');
	require_once(dirname(__FILE__).'/P.php');
	require_once(dirname(__FILE__).'/UnitTest.php');	

	// http://www.w3.org/TR/css3-syntax/
	
	// CSS3 productions rules
	$num		= P::choice(P::lexseq(P::kleene(P::dec()), P::char('.'), P::kleene(P::dec())), P::plus(P::dec()));	// num	::=	[0-9]+ | [0-9]* '.' [0-9]+
	$nl			= P::texts("\r \n", "\n", "\r", "\f");			// nl	::=	#xA | #xD #xA | #xD | #xC
	$nonascii 	= P::range(0x80, 0xFF); 						// nonascii	::=	[#x80-#xD7FF#xE000-#xFFFD#x10000-#x10FFFF]	Note: PHP only supports ISO-8859-1 strings
	$wc			= P::set(" \t\r\n\f");							// wc	::=	#x9 | #xA | #xC | #xD | #x20
	$w			= P::kleene($wc);								// w	::=	wc*
	$unicode 	= P::lexseq('\\', P::repeat(P::hex(), 1, 6), P::optional($wc));	// unicode	::=	'\' [0-9a-fA-F]{1,6} wc?
	$escape		= P::choice($unicode, P::lexseq('\\', P::choice(P::range(0x20, 0x7E), $nonascii)));	// escape	::=	unicode | '\' [#x20-#x7E#x80-#xD7FF#xE000-#xFFFD#x10000-#x10FFFF]
	$urlchar	= P::choice($escape, P::choice(0x09, 0x21, P::range(0x23, 0x26), P::range(0x28, 0x7E)), $nonascii);	// urlchar	::=	[#x9#x21#x23-#x26#x28-#x7E] | nonascii | escape
	$nmstart	= P::choice(P::alpha(), '_', $nonascii, $escape);			// nmstart	::=	[a-zA-Z] | '_' | nonascii | escape
	$nmchar		= P::choice(P::alnum(), '-', '_', $nonascii, $escape);		// nmchar	::=	[a-zA-Z0-9] | '-' | '_' | nonascii | escape
	$name		= P::plus($nmchar);											// name	::=	nmchar+
	$ident		= P::lexseq(P::optional('-'), $nmstart, P::kleene($nmchar));	// ident	::=	'-'? nmstart nmchar*
	$stringchar	= P::choice($urlchar, 0x20, P::lexseq('\\', $nl));				// stringchar	::=	urlchar | #x20 | '\' nl
	$string		= P::choice(P::lexseq('"', P::kleene(P::choice($stringchar, "'")), '"')
						,	P::lexseq("'", P::kleene(P::choice($stringchar, '"')), "'"));	// string	::=	'"' (stringchar | "'")* '"' | "'" (stringchar | '"')* "'"

	// new grammar
	$CDO				= P::text('<!--');
	$CDC				= P::text('-->');
	$INCLUDES			= P::text('~=');	
	$DASHMATCH			= P::text('|=');	
	$STRING				= $string;				
	$IDENT				= $ident;				
	$HASH				= P::lexseq('#', $name);
	$IMPORT_SYM			= P::text('@import');
	$PAGE_SYM			= P::text('@page');
	$MEDIA_SYM			= P::text('@media');
	$FONT_FACE_SYM		= P::text('@font-face');
	$CHARSET_SYM		= P::text('@charset');
	$NAMESPACE_SYM		= P::text('@namespace');
	$IMPORTANT_SYM		= P::seq('!', 'important');
	$EMS				= P::lexseq($num, "em");
	$EXS				= P::lexseq($num, "ex");
	$LENGTH				= P::lexseq($num, P::choice("px", "cm", "mm", "in", "pt", "pc"));
	$ANGLE				= P::lexseq($num, P::choice("deg", "rad", "grad"));
	$TIME				= P::lexseq($num, P::choice("ms", "s"));
	$FREQ				= P::lexseq($num, P::choice("Hz", "kHz"));
	$DIMEN				= P::lexseq($num, $ident);		// DIMENSION	::=	num ident	
	$PERCENTAGE			= P::lexseq($num, '%');			// PERCENTAGE	::=	num '%'
	$NUMBER				= $num;							// NUMBER	::=	num
	$URI				= P::seq('url(', P::choice($string, P::kleene($urlchar)), ')');	// URI	::=	"url(" w (string | urlchar* ) w ")"
	$FUNCTION			= P::lexseq($ident, '(');		// FUNCTION	::=	ident '('
	// Not quite sure on this one!
	$UNICODERANGE		= P::lexseq('U+', P::repeat(P::hex_upper(), 1, 6), P::optional(P::seq('-', P::repeat(P::hex_upper(), 1, 6))));	// UNICODE-RANGE	::=	"U+" [0-9A-F?]{1,6} ('-' [0-9A-F]{1,6})?

	// CSS Stylesheet grammar

	$COMMENT		= P::lexseq('/*', P::kleene(P::notset('*')), P::plus('*'), P::kleene(P::seq(P::notset('/'), P::kleene(P::notset('*')), P::plus('*'))), '/');	// COMMENT	::=	"/*" [^*]* '*'+ ([^/] [^*]* '*'+)* "/"
	$empty 			= P::choice(P::plus($wc), $COMMENT);
	
	// recursion stubs
	$expr				= P::stub();
	
	// Undefined in CSS spec:
	$font_family		= P::separated(',', P::choice($name, $STRING));
			
	// direct token descendents
	$operator 			= P::choice('/', ',', $empty);
	$attrib				= P::seq('[', $IDENT, P::optional(P::seq(P::choice('=', $INCLUDES, $DASHMATCH), P::choice($IDENT, $STRING))), ']');
	$combinator			= P::choice('+', '>', $empty);
	$prio				= $IMPORTANT_SYM;
	$pseudo				= P::lexseq(':', P::choice($IDENT, P::seq($FUNCTION, $IDENT, ')')));
	$unary_operator		= P::choice('-', '+');
	$class				= P::lexseq('.', $IDENT);
	$element_name		= P::choice($IDENT, '*');
	$pseudo_page		= P::lexseq(':', $IDENT);
	$namespace_prefix	= $IDENT;
	$namespace			= P::seq($NAMESPACE_SYM, P::optional($namespace_prefix), P::choice($STRING, $URI), ';');
	$property			= $IDENT;
	$medium				= $IDENT;
	$import				= P::seq($IMPORT_SYM, P::choice($STRING, $URI), P::optional(P::separated(',', $medium)), ';');	
	$hexcolor			= P::lexseq('#', P::choice(P::exact(P::hex(), 6), P::exact(P::hex(), 3)));
	$function			= P::seq($FUNCTION, $expr, ')');
	$term				= P::choice(P::seq(P::optional($unary_operator), P::choice($NUMBER, $PERCENTAGE, $LENGTH, $EMS, $EXS, $ANGLE, $TIME, $FREQ, $function))
									, $font_family, $STRING, $IDENT, $URI, $UNICODERANGE, $hexcolor);
	$simple_selector	= P::lexseq(P::optional($element_name), P::kleene(P::choice($HASH, $class, $attrib, $pseudo)));
	$selector			= P::separated($combinator, $simple_selector);	
	$declaration		= P::optional(P::seq(P::lexseq($property, ':'), $expr, P::optional($prio)));
	$page				= P::seq($PAGE_SYM, P::optional($IDENT), '{', P::separated(';', $declaration), '}'); 
	$font_face			= P::seq($FONT_FACE_SYM, '{', P::separated(';', $declaration), '}'); 
	$ruleset			= P::seq(P::separated(',', $selector), '{', P::separated(';', $declaration), '}'); 	
	$media				= P::seq($MEDIA_SYM, P::separated(',', $medium), '{', P::kleene($ruleset), '}');
	$stylesheet			= P::seq( P::optional(P::seq($CHARSET_SYM, $STRING, ';'))
								, P::kleene(P::choice($CDO, $CDC))
								, P::kleene(P::seq($import, P::kleene(P::choice($CDO, $CDC))))
								, P::kleene(P::seq($namespace, P::kleene(P::choice($CDO, $CDC))))
								, P::kleene(P::seq(P::choice($ruleset, $media, $page, $font_face), P::kleene(P::choice($CDO, $CDC)))));

	// assign recursions TODO separated
	$expr->parser		= P::separated($operator, $term);
		
	$ws = P::context($empty);
	
	testParserContext($medium, '-a', $ws, TRUE, 2);
	testParserContext($medium, 'a123', $ws, TRUE, 4);
	testParserContext($medium, '123a', $ws, FALSE, 0);
	testParserContext($hexcolor, '#123', $ws, TRUE, 4);
	testParserContext($hexcolor, '#123456', $ws, TRUE, 7);
	testParserContext($hexcolor, '#12345', $ws, TRUE, 4);
	testParserContext($attrib, '[type]', $ws, TRUE, 6);
	testParserContext($attrib, '[type="text"]', $ws, TRUE, 13);
	testParserContext($attrib, '[type~=text]', $ws, TRUE, 12);
	testParserContext($attrib, "[type|='text']", $ws, TRUE, 14);
	testParserContext($attrib, "[ type |= 'text' ]", $ws, TRUE, 18);
	
	testParserContext($element_name, "BODY", $ws, TRUE, 4);
	testParserContext($simple_selector, "BODY", $ws, TRUE, 4);
	testParserContext($selector, "BODY", $ws, TRUE, 4);
	testParserContext($property, "background:", $ws, TRUE, 10);
	testParserContext($term, "'url'", $ws, TRUE, 5);
	testParserContext($font_family, "Arial, Verdana, Helvetica", $ws, TRUE, 25);	
	testParserContext($term, "Arial, Verdana, Helvetica", $ws, TRUE, 25);	
	testParserContext($term, "#ffffff", $ws, TRUE, 7);	
	testParserContext($expr, "#ffffff", $ws, TRUE, 7);	
	testParserContext($declaration, "background: #ffffff", $ws, TRUE, 19);
	testParserContext($declaration, "font-family:	Arial, Verdana, Helvetica", $ws, TRUE, 38);
	testParserContext($ruleset, "BODY{}", $ws, TRUE, 6);
	testParserContext($ruleset, "BODY{background: #ffffff;}", $ws, TRUE, 26);
	testParserContext($ruleset, "BODY{background: #ffffff;color:#000000;}", $ws, TRUE, 40);
	testParserContext($ruleset, "BODY{font-family:	Arial, Verdana, Helvetica;}", $ws, TRUE, 45);
 
	$css = 'BODY		{
		background:	#ffffff;
		color:		#000000;
		font-family:	Arial, Verdana, Helvetica;
		}';	
	
	testParserContext($stylesheet, $css, $ws, TRUE, 99);
	
	UnitTest::report();
	
	$tokens = array();
	//array(&$this, &$in, $offset, $match->length)
	function addToken($token) {
		return create_function('', 'global $tokens; $tokens[] = $token;');
	}
	
?>