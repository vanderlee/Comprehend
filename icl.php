<?php

	header('Content-Type: text/html; charset=utf-8');

	require_once(dirname(__FILE__).'/Parser.php');
	require_once(dirname(__FILE__).'/P.php');
	require_once(dirname(__FILE__).'/UnitTest.php');

	$units	= P::texts('px', '%', 'vw', 'vh', 'vmin', 'vmax');
	$optdigits = P::kleene(P::dec());
	$digits = P::plus(P::dec());
	$float	= P::choice(P::lexseq($digits, '.', $optdigits), P::lexseq('.', $digits), $digits);	// num	::=	[0-9]+ '.' [0-9]* | [0-9]+


	$tokens = array();

	$float->onMatch(function($blah, $in, $offset, $length) use(&$tokens) {
		$tokens[] = array('FLOAT', substr($in, $offset, $length));
	});

	testParser($float, '12.3aaa', TRUE, 4);
	testParser($float, '123', TRUE, 3);
	testParser($float, '.123', TRUE, 4);
	testParser($float, '.', FALSE, 1);
	testParser($float, '0.0', TRUE, 3);

	var_dump($tokens);

	UnitTest::report();