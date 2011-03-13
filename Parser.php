<?php

	/**
	 * phpOOParser
	 * LALR(1) grammar parser framework, ISO-8895-1 support only due to PHP limitations on native string format.
	 * @version 0.0.1
	 * @author Martijn W. van der Lee (martijn-at-vanderlee-dot-com)
	 * @copyright Copyright (c) 2011, Martijn W. van der Lee
	 * @license http://www.opensource.org/licenses/mit-license.php
	 */
	 
	//TODO Directive leftmost/shortest/longest for OrParser.
	//TODO Directive case-sensitive/insensitive for Char/Text/Range/Set/Preg (all terminals).
	//TODO Pass along context with directive settings and skipper.
	//TODO Deprecate separate longest/shortest parser alternatives for composed OrParsers in P class.

	class ParserUtil {
		private function __construct() {}	// Pure static
		
		private static function array_flatten($a, $f = array()){
			if (!$a || !is_array($a)) {
				return '';
			}
			foreach($a as $k => $v) {
				if (is_array($v)) {
					$f = self::array_flatten($v, $f);
				} else {
					$f[$k] = $v;
				}
			}	
			return $f;
		}
	
		public static function getParserArgs($args) {
			$parsers = array();
			foreach (self::array_flatten($args) as $arg) {
				if (($parser = ParserUtil::getParserArg($arg)) !== FALSE) {
					$parsers[] = $parser;
				}
			}
			return $parsers;
		}
		
		public static function getParserArg($arg) {
			// Get very first non-array value of (recursive) array
			while (is_array($arg)) {
				$arg = reset($arg);
			}

			if (is_string($arg)) {
				switch (strlen($arg)) {
					case 0:		return FALSE;
					case 1:		return new CharParser($arg);
					default:	return new TextParser($arg);
				}
			} else if (is_int($arg)) {
				return new CharParser(ParserUtil::getCharArg($arg));
			}
			return $arg;	// assume parser or fail normally later on
		}
		
		public static function getCharArg($arg) {
			if (is_numeric($arg)) {
				return chr($arg);
			}
			return $arg[0];
		}		
	}
	
	class ParserContext {
		// Contains skipper class and functionality
		protected static $skipper = null;
		public static function setSkipper(Parser $skipper)	{	self::$skipper = $skipper;	}
		protected function skip($in, $offset) {
			if ((self::$skipper instanceof Parser) && ($skip = self::$skipper->parse($in, $offset)) !== FALSE) {	// skipper always optional
				return $skip;
			}
			return 0;
		}		
	}
	
	abstract class Parser {
		protected static $skipper = null;
		public static function setSkipper(Parser $skipper)	{	self::$skipper = $skipper;	}
		protected function skip($in, $offset) {
			if ((self::$skipper instanceof Parser) && ($skip = self::$skipper->parse($in, $offset)) !== FALSE) {	// skipper always optional
				return $skip;
			}
			return 0;
		}
		
		abstract protected function doParse($in, $offset);
		
		public function parse($in, $offset = 0) {
			// onBefore
			foreach ((array)$this->before_listeners as $before_listener) {
				call_user_func_array($before_listener, array(&$this, &$in, $offset));
			}
			
			if (($length = $this->doParse($in, $offset)) === FALSE) {
				// onMismatch
				foreach ((array)$this->mismatch_listeners as $mismatch_listener) {
					call_user_func_array($mismatch_listener, array(&$this, &$in, $offset));
				}
			} else {
				// onMatch
				foreach ((array)$this->match_listeners as $match_listener) {
					call_user_func_array($match_listener, array(&$this, &$in, $offset, &$length));
				}
			}
			
			// onAfter
			foreach ((array)$this->after_listeners as $after_listener) {
				call_user_func_array($after_listener, array(&$this, &$in, $offset, &$length));
			}
			
			return $length;			
		}
		
		private $match_listeners = array();
		public function onMatch($match_listener) {
			$this->match_listeners[] = $match_listener;
		}
		
		private $mismatch_listeners = array();
		public function onMismatch($mismatch_listener) {
			$this->mismatch_listeners[] = $mismatch_listener;
		}
		
		private $before_listeners = array();
		public function onBefore($before_listener) {
			$this->before_listeners[] = $before_listener;
		}		
		
		private $after_listeners = array();
		public function onAfter($after_listener) {
			$this->after_listeners[] = $after_listener;
		}
	}
	
	class LexemeParser extends Parser {
		private $parser = null;
		public function __construct(Parser $parser) {
			$this->parser = $parser;
		}	
		protected function doParse($in, $offset) {
			$skipper = self::$skipper;
			self::$skipper = null;
			return $this->parser->parse($in, $offset);
			self::$skipper = $skipper;
		}
	}
	
	class AnyParser extends Parser {
		public function __construct() {}
		protected function doParse($in, $offset) {
			if ($offset < strlen($in)) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class CharParser extends Parser {
		private $char = null;
		public function __construct($char) {
			$this->char = ParserUtil::getCharArg($char);
		}
		protected function doParse($in, $offset) {
			if (strlen($this->char) <= 0) return FALSE;

			if ($offset >= strlen($in))	return FALSE;
		
			if ($in[$offset] == $this->char) {
				return 1; 
			}
			return FALSE;
		}
	}
	
	class TextParser extends Parser {
		private $text 	= null;
		private $length = null;
		public function __construct($text) {
			$this->text = $text;
			$this->length = strlen($text);
		}
		protected function doParse($in, $offset) {
			if ($this->length <= 0) return FALSE;

			if ($offset + $this->length > strlen($in))	return FALSE;
			
			if (substr($in, $offset, $this->length) == $this->text) {
				return $this->length; 
			}
			return FALSE;
		}
	}

	class RangeParser extends Parser {
		private $first	= null;
		private $last	= null;
		public function __construct($first, $last) {
			$this->first	= empty($first)? null : ord(ParserUtil::getCharArg($first));
			$this->last		= empty($last)? null : ord(ParserUtil::getCharArg($last));	
		}
		protected function doParse($in, $offset) {
			if ($this->first === null && $this->last === null) return FALSE;
		
			if ($offset >= strlen($in)) return FALSE;
			
			$ord = ord($in[$offset]);
			if ($this->first <= $ord && ($this->last === null || $ord <= $this->last)) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class SetParser extends Parser {
		private $set = null;
		public function __construct($set) {
			$this->set = $set;
		}
		protected function doParse($in, $offset) {
			if ($offset >= strlen($in))	return FALSE;
		
			if (strchr($this->set, $in[$offset]) !== FALSE) {
				return 1;
			}
			return FALSE;
		}
	}
	
	class PregParser extends Parser {
		private $pattern = null;
		public function __construct($pattern) {
			$this->pattern = $pattern;
		}
		protected function doParse($in, $offset) {
			if (preg_match($this->pattern, substr($in, $offset), $m) !== FALSE) {
				if (strpos($in, $m[0], $offset) == $offset) {
					return strlen($m[0]);
				}
			}
			return FALSE;
		}	
	}

	// Multi parser;
	class RepeatParser extends Parser {
		private $parser	= null;
		private $min	= null;
		private $max 	= null;
		public function __construct($parser, $min = 0, $max = null) {
			$this->parser = ParserUtil::getParserArg($parser);
			$this->min = $min;
			$this->max = $max;
		}
		protected function doParse($in, $offset) {
			$total = 0;
			$matches = 0;
			do {
				$total += $this->skip($in, $offset);
				if (($length = $this->parser->parse($in, $offset + $total)) !== FALSE) {
					$total += $length;
					++$matches;
				}
			} while ($length !== FALSE && ($this->max == null || $matches < $this->max));
						
			return ($matches >= $this->min && ($this->max == null || $matches <= $this->max))? $total : FALSE;
		}		
	}	

	// Array Parsers	
	class OrParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			foreach ($this->parsers as $parser) {
				if (($return = $parser->parse($in, $offset)) !== FALSE) {
					return $return;
				}
			}
			return FALSE;
		}		
	}
	
	/**
	 * AndParser
	 * Matches the longest input matched by all child parsers or returns FALSE is atleast one does not match.
	 */
	class AndParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = PHP_INT_MAX;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) === FALSE) {
					return FALSE;
				} else {
					$return = min($return, $return_sub);
				}
			}
			return $return;
		}		
	}
	
	class LongestParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = FALSE;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) !== FALSE) {
					$return = ($return === FALSE? $return_sub : max($return, $return_sub));
				}
			}
			return $return;
		}		
	}
	
	class ShortestParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$return = FALSE;
			foreach ($this->parsers as $parser) {
				if (($return_sub = $parser->parse($in, $offset)) !== FALSE) {
					$return = ($return === FALSE? $return_sub : min($return, $return_sub));
				}
			}
			return $return;
		}		
	}
	
	class SequenceParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset) {
			$total = 0;
			foreach ($this->parsers as $parser) {
				$total += $this->skip($in, $offset);
				if (($length = $parser->parse($in, $offset + $total)) === FALSE) {		// must match
					return FALSE;
				}
				$total += $length;
			}
			return $total;
		}		
	}
	
	/**
	 * NotParser
	 * Use with caution. You shouldn't need to use this with BNF grammars
	 */
	class NotParser extends Parser {
		private $parser	= null;
		public function __construct($parser) {
			$this->parser = ParserUtil::getParserArg($parser);
		}
		protected function doParse($in, $offset) {
			if ($this->parser->parse($in, $offset) === FALSE) {
				return 0;
			}
			return FALSE;
		}
	}
	
	class ExceptParser extends Parser {
		private $parser_match	= null;
		private $parser_not		= null;
		public function __construct($parser_match, $parser_not) {
			$this->parser_match	= ParserUtil::getParserArg($parser_match);
			$this->parser_not	= ParserUtil::getParserArg($parser_not);
		}
		protected function doParse($in, $offset) {
			if (($length = $this->parser_match->parse($in, $offset)) !== FALSE
			 && $this->parser_not->parse($in, $offset) === FALSE) {
				return $length;
			}
			return FALSE;
		}	
	}
?>