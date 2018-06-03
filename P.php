<?php

class P {

	// Stub
	public static function stub()
	{
		return new \vanderlee\comprehend\parser\Stub();
	}

	// Directives
	public static function context($skipper = NULL, $case_sensitive = TRUE)
	{
		return new \vanderlee\comprehend\core\Context($skipper, $case_sensitive);
	}

	public static function lexeme($parser)
	{
		return new \vanderlee\comprehend\directive\Lexeme($parser);
	}

	public static function case_sensitive($parser)
	{
		return new \vanderlee\comprehend\directive\CaseSensitive($parser, TRUE);
	}

	public static function case_insensitive($parser)
	{
		return new \vanderlee\comprehend\directive\CaseSensitive($parser, FALSE);
	}

	public static function or_mode_first($parser)
	{
		return new \vanderlee\comprehend\directive\Choice($parser, \vanderlee\comprehend\core\Context::OR_FIRST);
	}

	public static function or_mode_longest($parser)
	{
		return new \vanderlee\comprehend\directive\Choice($parser, \vanderlee\comprehend\core\Context::OR_LONGEST);
	}

	public static function or_mode_shortest($parser)
	{
		return new \vanderlee\comprehend\directive\Choice($parser, \vanderlee\comprehend\core\Context::OR_SHORTEST);
	}

	// Terminals
	public static function any()
	{
		return new \vanderlee\comprehend\parser\Any();
	}

	public static function char($char)
	{
		return new \vanderlee\comprehend\parser\Char($char);
	}

	public static function text($text)
	{
		return new \vanderlee\comprehend\parser\Text($text);
	}

// token

	public static function texts()
	{
		$texts = array();		// tokens
		foreach (func_get_args() as $text) {
			$texts[] = new \vanderlee\comprehend\parser\Text($text);
		}
		return new \vanderlee\comprehend\parser\Choice($texts);
	}

	public static function range($first, $last)
	{
		return new \vanderlee\comprehend\parser\Range($first, $last);
	}

	public static function set($set)
	{
		return new \vanderlee\comprehend\parser\Set($set);
	}

	public static function notset($set)
	{
		return new \vanderlee\comprehend\parser\Except(new \vanderlee\comprehend\parser\Any(), new \vanderlee\comprehend\parser\Set($set));
	}

	public static function preg($pattern)
	{
		return new \vanderlee\comprehend\parser\Regex($pattern);
	}

	public static function whitespace()
	{
		return new \vanderlee\comprehend\parser\Regex('~\s+~');
	}

	// Multiple	
	public static function repeat($p, $n, $m)
	{
		return new \vanderlee\comprehend\parser\Repeat($p, $n, $m);
	}

// multi, multiple

	public static function exact($p, $n)
	{
		return new \vanderlee\comprehend\parser\Repeat($p, $n, $n);
	}

// times

	public static function kleene($p)
	{
		return new \vanderlee\comprehend\parser\Repeat($p, 0, NULL);
	}

// star

	public static function plus($p)
	{
		return new \vanderlee\comprehend\parser\Repeat($p, 1, NULL);
	}

// many, more

	public static function optional($p)
	{
		return new \vanderlee\comprehend\parser\Repeat($p, 0, 1);
	}

// opt, one_or_zero

	public static function separated($separator, $p)
	{
		return new \vanderlee\comprehend\parser\Sequence($p, new \vanderlee\comprehend\parser\Repeat(new \vanderlee\comprehend\parser\Sequence($separator, $p), 0, NULL));
	}

	// Flow
	public static function seq()
	{
		return new \vanderlee\comprehend\parser\Sequence(func_get_args());
	}

	public static function lexseq()
	{
		return new \vanderlee\comprehend\directive\Lexeme(new \vanderlee\comprehend\parser\Sequence(func_get_args()));
	}

	public static function choice()
	{
		return new \vanderlee\comprehend\parser\Choice(func_get_args());
	}

	public static function first()
	{
		return new \vanderlee\comprehend\directive\Choice(new \vanderlee\comprehend\parser\Choice(func_get_args()), \vanderlee\comprehend\core\Context::OR_FIRST);
	}

	public static function longest()
	{
		return new \vanderlee\comprehend\directive\Choice(new \vanderlee\comprehend\parser\Choice(func_get_args()), \vanderlee\comprehend\core\Context::OR_LONGEST);
	}

	public static function shortest()
	{
		return new \vanderlee\comprehend\directive\Choice(new \vanderlee\comprehend\parser\Choice(func_get_args()), \vanderlee\comprehend\core\Context::OR_SHORTEST);
	}

	public static function all()
	{
		return new \vanderlee\comprehend\parser\Any(func_get_args());
	}

	public static function not()
	{
		return new \vanderlee\comprehend\parser\Not(func_get_args());
	}

	public static function except($p_a, $p_b)
	{
		return new \vanderlee\comprehend\parser\Except($p_a, $p_b);
	}

	// Predefined terminals
	public static function bin()
	{
		return new \vanderlee\comprehend\parser\Set('01');
	}

	public static function oct()
	{
		return new \vanderlee\comprehend\parser\Set('01234567');
	}

	public static function dec()
	{
		return new \vanderlee\comprehend\parser\Set('0123456789');
	}

	public static function hex()
	{
		return new \vanderlee\comprehend\parser\Set('0123456789abcdefABCDEF');
	}

	public static function hex_upper()
	{
		return new \vanderlee\comprehend\parser\Set('0123456789ABCDEF');
	}

	public static function hex_lower()
	{
		return new \vanderlee\comprehend\parser\Set('0123456789abcdef');
	}

	public static function alpha()
	{
		return new \vanderlee\comprehend\parser\Regex('~[[:alpha:]]~');
	}

	public static function alnum()
	{
		return new \vanderlee\comprehend\parser\Regex('~[[:alnum:]]~');
	}

	public static function printable()
	{
		return new \vanderlee\comprehend\parser\Regex('~[[:print:]]~');
	}

	// reserved keyword aliasses (requires PHP 5.2.3+)
	public static function __callStatic($method, $args)
	{
		switch ($method) {
			case 'or': return self::first($args);
			case 'and': return self::all($args);
		}
		return FALSE;
	}

}
