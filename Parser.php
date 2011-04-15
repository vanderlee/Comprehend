<?php

	/**
	 * phpOOParser
	 * LALR(1) grammar parser framework, ISO-8895-1 support only due to PHP limitations on native string format.
	 * @version 0.0.1
	 * @author Martijn W. van der Lee (martijn-at-vanderlee-dot-com)
	 * @copyright Copyright (c) 2011, Martijn W. van der Lee
	 * @license http://www.opensource.org/licenses/mit-license.php
	 */

	//TODO Implement the token stuff in everything after sequence	OR-Shortest, Repeat
	//TODO Handle directives inside ParserContext, not by push/pop
	//TODO Errors on invalid input; die ASAP.
	//TODO Return best match length on fail in OrParser for longest/shortest modes
	//TODO Have the ParserContext "stream" the data to the Parsers; allows input other than pure string. -> may also be easy way to implement case insensitivity.
		// Pull from stream?! -> getData($offset, $length)
		// Sufficient benefit?
			// - Lower memory profile for files (though trackback!)
			// - Easy alternative input streams (binary, hexadecimal, bytestream, UTF-8???)
	
	
	class ParserToken {
		public $token;
		public $offset;
		public $length;
		
		public function __construct($token, $offset, $length) {
			$this->token	= $token;
			$this->offset	= $offset;
			$this->length	= $length;
		}
	}
	
	class ParserMatch {
		public $match;		
		public $length;
		public $tokens;
		
		public function __construct($match, $length, $tokens = array()) {
			$this->match	= $match;
			$this->length	= $length;
			$this->tokens	= $tokens;
		}
	}
	
	class ParserUtil {
		private function __construct() {}	// Pure static
				
		public static function getCharArg($arg) {
			if (empty($arg)) {
				return FALSE;
			} else if (is_numeric($arg)) {
				return chr($arg);
			}
			return $arg;
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
			} else if ($arg instanceof Parser) {
				return $arg;
			}
			
			echo 'INVALID ARGUMENT: ';
			debug_print_backtrace();
			die;
		}
		
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
	
		public static function getParserArgs() {
			$parsers = array();
			foreach ((array)self::array_flatten(func_get_args()) as $arg) {
				if (($parser = ParserUtil::getParserArg($arg)) === FALSE) {
					return FALSE;
				}				
				$parsers[] = $parser;
			}
			return $parsers;
		}
	}
	
	/**
	 * Maintains the current context of the parser chain
	 */
	class ParserContext {
		private $skipper;
		public function pushSkipper($skipper = null) {
			array_push($this->skipper, $skipper === null? null : ParserUtil::getParserArg($skipper));
		}
		public function popSkipper()	{
			array_pop($this->skipper);
		}
		public function skip($in, $offset) {
			$skipper = end($this->skipper);			
			if ($skipper instanceof Parser) {
				$match = $skipper->parse($in, $offset, new ParserContext());			
				if ($match->match) {
					return $match->length;
				}
			}
			return 0;
		}
		
		private $case_sensitive;
		public function pushCaseSensitive($case_sensitive = TRUE) {
			array_push($this->case_sensitive, (bool)$case_sensitive);
		}
		public function popCaseSensitive()	{
			array_pop($this->case_sensitive);
		}
		public function isCaseSensitive() {
			return end($this->case_sensitive);
		}
		// Helper
		public function handleCase($text) {
			return $this->isCaseSensitive()? $text : strtolower($text);
		}
				
		const OR_FIRST		= 0x00;
		const OR_LONGEST	= 0x01;
		const OR_SHORTEST	= 0x02;
				
		private $or_mode;
		public function pushOrMode($or_mode) {
			array_push($this->or_mode, $or_mode);
		}
		public function popOrMode()	{
			array_pop($this->or_mode);
		}
		public function getOrMode() {
			return end($this->or_mode);
		}
		public function __construct($skipper = null, $case_sensitive = TRUE, $or_mode = self::OR_FIRST) {
			$this->skipper			= array();
			$this->case_sensitive	= array();
			$this->or_mode			= array();
			
			$this->pushSkipper($skipper);
			$this->pushCaseSensitive($case_sensitive);
			$this->pushOrMode($or_mode);
		}				
	}

	abstract class Parser {	
		const INVALID_ARGUMENTS = -1;
	
		abstract protected function doParse($in, $offset, ParserContext $context);
		
		public function parse($in, $offset = 0, ParserContext $context = null) {
			// DEBUGGING:
			//echo get_class($this).'<br/>'; flush();
		
			if ($context === null) {
				$context = new ParserContext();
			}
			
			// onBefore
			foreach ((array)$this->before_listeners as $before_listener) {
				call_user_func_array($before_listener, array(&$this, &$in, $offset));
			}
			
			$match = $this->doParse($in, $offset, $context);
			
			if ($match->match) {
				// onMatch
				foreach ((array)$this->match_listeners as $match_listener) {
					call_user_func_array($match_listener, array(&$this, &$in, $offset, $match->length));
				}
			} else {
				// onMismatch
				foreach ((array)$this->mismatch_listeners as $mismatch_listener) {
					call_user_func_array($mismatch_listener, array(&$this, &$in, $offset, $match->length));
				}
			}
			
			// onAfter
			foreach ((array)$this->after_listeners as $after_listener) {
				call_user_func_array($after_listener, array(&$this, &$in, $offset, $match->length));
			}
			
			return $match;			
		}
		
		private $match_listeners = array();
		public function onMatch($match_listener) {
			$this->match_listeners[] = $match_listener;
			return $this; // chain
		}
		
		private $mismatch_listeners = array();
		public function onMismatch($mismatch_listener) {
			$this->mismatch_listeners[] = $mismatch_listener;
			return $this; // chain
		}
		
		private $before_listeners = array();
		public function onBefore($before_listener) {
			$this->before_listeners[] = $before_listener;
			return $this; // chain
		}		
		
		private $after_listeners = array();
		public function onAfter($after_listener) {
			$this->after_listeners[] = $after_listener;
			return $this; // chain
		}
		
		private $token = null;
		public function token($token) {
			$this->token = $token;
			return $this; // chain
		}
		protected function hasToken() {
			return $this->token !== null;
		}
		protected function makeToken($offset, $length) {
			if ($this->token === null) {
				return null;
			}
			return array(new ParserToken($this->token, $offset, $length));
		}
	}

	// Directives
	abstract class Directive extends Parser {}
	
	class LexemeDirective extends Directive {
		private $parser = null;
		public function __construct($parser) {
			$this->parser = ParserUtil::getParserArg($parser);
		}	
		protected function doParse($in, $offset, ParserContext $context) {
			$context->pushSkipper();
			$match = $this->parser->parse($in, $offset, $context);
			$context->popSkipper();
			return $match;
		}
	}
	
	class CaseDirective extends Directive {
		private $parser = null;
		private $case_sensitive = null;
		
		public function __construct($parser, $case_sensitive) {
			$this->parser			= ParserUtil::getParserArg($parser);
			$this->case_sensitive	= (bool)$case_sensitive;
		}	
		
		protected function doParse($in, $offset, ParserContext $context) {
			$context->pushCaseSensitive($this->case_sensitive);
			$match = $this->parser->parse($in, $offset, $context);
			$context->popCaseSensitive();
			return $match;
		}
	}
	
	class OrDirective extends Directive {
		private $parser = null;
		private $or_mode = null;
		
		public function __construct($parser, $or_mode) {
			$this->parser	= ParserUtil::getParserArg($parser);
			$this->or_mode	= $or_mode;
		}	
		
		protected function doParse($in, $offset, ParserContext $context) {
			$context->pushOrMode($this->or_mode);
			$match = $this->parser->parse($in, $offset, $context);
			$context->popOrMode();
			return $match;
		}
	}


	// Stub
	
	class StubParser extends Parser {
		private $parser = null;
		public function __construct() {}	
		public function __set($name, $parser) {
			if ($name == 'parser') {
				$this->parser = ParserUtil::getParserArg($parser);
			}
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if ($this->parser === null) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
			$match = $this->parser->parse($in, $offset, $context);
			if ($match->match && $this->hasToken()) {
				$match->token = $this->makeToken($offset, $match->length);
			}
			return $match;
		}
	}
		
	// Terminals
	
	class AnyParser extends Parser {
		public function __construct() {}
		protected function doParse($in, $offset, ParserContext $context) {
			if ($offset < strlen($in)) {
				return new ParserMatch(TRUE, 1, $this->makeToken($offset, 1));
			}
			return new ParserMatch(FALSE, 0);
		}
	}
	
	class CharParser extends Parser {
		private $char = null;
		public function __construct($char) {
			$this->char = ParserUtil::getCharArg($char);
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if (strlen($this->char) != 1) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

			if ($offset >= strlen($in))	return new ParserMatch(FALSE, 0);
		
			if ($context->handleCase($in[$offset]) == $context->handleCase($this->char)) {
				return new ParserMatch(TRUE, 1, $this->makeToken($offset, 1)); 
			}
			return new ParserMatch(FALSE, 0);
		}
	}
	
	class TextParser extends Parser {
		private $text 	= null;
		private $length = null;
		public function __construct($text) {
			$this->text = $text;
			$this->length = strlen($text);
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if ($this->length <= 0) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
			
			$text = $context->handleCase($this->text);
			for ($c = 0; $c < $this->length; $c++) {
				if ($offset + $c >= strlen($in)
				 || $text[$c] != $context->handleCase($in[$offset + $c])) {
					return new ParserMatch(FALSE, $c);
				}
			}
			return new ParserMatch(TRUE, $this->length, $this->makeToken($offset, $this->length));
		}
	}

	class RangeParser extends Parser {
		private $first	= null;
		private $last	= null;
		public function __construct($first, $last) {
			$this->first	= empty($first)? null : ParserUtil::getCharArg($first);
			$this->last		= empty($last)? null : ParserUtil::getCharArg($last);	
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if ($this->first === null && $this->last === null) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
		
			if ($offset >= strlen($in)) return new ParserMatch(FALSE, 0);
			
			$first	= ord($context->handleCase($this->first));
			$last	= ord($context->handleCase($this->last));			
			$ord	= ord($context->handleCase($in[$offset]));
			if ($first <= $ord && ($this->last === null || $ord <= $last)) {
				return new ParserMatch(TRUE, 1, $this->makeToken($offset, 1));
			}
			return new ParserMatch(FALSE, 0);
		}
	}

	class SetParser extends Parser {
		private $set = null;
		public function __construct($set) {
			$this->set = $set;
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if (strlen($this->set) <= 0) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);

			if ($offset >= strlen($in))	return new ParserMatch(FALSE, 0);
		
			if (strchr($context->handleCase($this->set), $context->handleCase($in[$offset])) !== FALSE) {
				return new ParserMatch(TRUE, 1, $this->makeToken($offset, 1));
			}
			return new ParserMatch(FALSE, 0);
		}
	}

	class PregParser extends Parser {
		private $pattern = null;
		public function __construct($pattern) {
			$this->pattern = $pattern;
		}
		protected function doParse($in, $offset, ParserContext $context) {
			// At the very least, a pattern should have delimiters.
			if (strlen($this->pattern) <= 2) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
			
			$pattern = $this->pattern.($context->isCaseSensitive()? '' : 'i');
			
			if (preg_match($pattern, $in, $m, 0, $offset) !== FALSE) {
				if (count($m) > 0 && strpos($in, $m[0], $offset) == $offset) {
					return new ParserMatch(TRUE, strlen($m[0]), $this->makeToken($offset, strlen($m[0])));
				}
			}
			return new ParserMatch(FALSE, 0);
		}	
	}

	// Flow
	
	class SequenceParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset, ParserContext $context) {
			$tokens = array();
		
			if (!is_array($this->parsers)
			 || count($this->parsers) < 1) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
		
			$total = 0;
			foreach ($this->parsers as $parser) {
				$total += $context->skip($in, $offset + $total);
				$match = $parser->parse($in, $offset + $total, $context);
				$total += $match->length;
				if (!$this->hasToken() && count($match->tokens)) {
					$tokens = array_merge($tokens, $match->tokens);
				}
				if (!$match->match) {		// must match
					return new ParserMatch(FALSE, $total);
				}
			}
			return new ParserMatch(	TRUE
								,	$total
								,	$this->hasToken()? $this->makeToken($offset, $total) : $tokens);
		}		
	}
		
	// Repeat
	
	class RepeatParser extends Parser {
		private $parser	= null;
		private $min	= null;
		private $max 	= null;
		public function __construct($parser, $min = 0, $max = null) {
			$this->parser = ParserUtil::getParserArg($parser);
			$this->min = $min;
			$this->max = $max;
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if ($this->max !== null && $this->max < $this->min) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
		
			$total = 0;
			$matches = 0;
			$tokens = array();
			do {
				$skip = $context->skip($in, $offset + $total);
				$match = $this->parser->parse($in, $offset + $total + $skip, $context);
				if ($match->match) {
					if (!$this->hasToken() && count($match->tokens)) {
						$tokens = array_merge($tokens, $match->tokens);
					}
					$total += $skip + $match->length;
					++$matches;
				}
			} while ($match->match && ($this->max == null || $matches < $this->max));
						
			return new ParserMatch(	$matches >= $this->min && ($this->max == null || $matches <= $this->max)
								,	$total
								,	$this->hasToken()? $this->makeToken($offset, $total) : $tokens);
		}		
	}
	
	// Choice
	
	class OrParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset, ParserContext $context) {
			if (!is_array($this->parsers)
			 || count($this->parsers) < 1) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
		
			switch ($context->getOrMode()) {
				default:
				case ParserContext::OR_FIRST:
					$max = 0;
					foreach ($this->parsers as $parser) {
						$match = $parser->parse($in, $offset, $context);
						if ($match->match) {
							return $match;
						}
						$max = max($max, $match->length);
					}
					return new ParserMatch(FALSE, $max);
				break;
				
				case ParserContext::OR_LONGEST:
					$max_match	= new ParserMatch(FALSE, 0);
					foreach ($this->parsers as $parser) {
						$match = $parser->parse($in, $offset, $context);
						if ($match->match == $max_match->match) {
							if ($match->length > $max_match->length) { 
								$max_match		= $match;
							}
						} else if ($match->match) {
							$max_match = $match;
						}
					}
					return $max_match;
				
				/*
					$isMatch	= FALSE;
					$length		= 0;
					foreach ($this->parsers as $parser) {
						$match = $parser->parse($in, $offset, $context);
						if ($match->match == $isMatch) {
							$length		= max($length, $match->length);
						} else if ($match->match) {
							$isMatch	= TRUE;
							$length		= $match->length;
						}
					}
					return new ParserMatch($isMatch, $length);			*/	
				break;
				
				case ParserContext::OR_SHORTEST:
					$isMatch	= FALSE;
					$length		= 0;
					foreach ($this->parsers as $parser) {
						$match = $parser->parse($in, $offset, $context);
						if ($match->match && !$isMatch) {
							$isMatch	= TRUE;
							$length		= $match->length;
						} else if ($match->match) {
							$length		= min($length, $match->length);
						}
					}
					
					return new ParserMatch($isMatch, $length);				
				break;
			}
		}		
	}
	
	// Logic
	
	/**
	 * AndParser
	 * Matches the longest input matched by all child parsers or returns FALSE is atleast one does not match.
	 */
	class AndParser extends Parser {
		private $parsers = null;
		public function __construct() {
			$this->parsers = ParserUtil::getParserArgs(func_get_args());
		}
		protected function doParse($in, $offset, ParserContext $context) {
			// Atleast two terms
			if (count($this->parsers) < 2) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
			
			$length = PHP_INT_MAX;
			foreach ($this->parsers as $parser) {
				$match = $parser->parse($in, $offset, $context);
				if (!$match->match) {
					return new ParserMatch(FALSE, 0);
				} else {
					$length = min($length, $match->length);
				}
			}
			return new ParserMatch(TRUE, $length);
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
		protected function doParse($in, $offset, ParserContext $context) {
			if (!$this->parser instanceof Parser) return new ParserMatch(FALSE, Parser::INVALID_ARGUMENTS);
			
			$match = $this->parser->parse($in, $offset, $context);
			return new ParserMatch(!$match->match, $match->length);
		}
	}
	
	class ExceptParser extends Parser {
		private $parser_match	= null;
		private $parser_not		= null;
		public function __construct($parser_match, $parser_not) {
			$this->parser_match	= ParserUtil::getParserArg($parser_match);
			$this->parser_not	= ParserUtil::getParserArg($parser_not);
		}
		protected function doParse($in, $offset, ParserContext $context) {
			$match 	= $this->parser_match->parse($in, $offset, $context);
			$not	= $this->parser_not->parse($in, $offset, $context);
			if ($match->match && !$not->match) {
				return $match;
			}
			return new ParserMatch(FALSE, min($match->length, $not->length));
		}	
	}
?>