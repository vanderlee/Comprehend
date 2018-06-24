<?php

namespace vanderlee\comprehend\parser;

/**
 *
 * @author Martijn
 */
trait AssignTrait {

	/**
	 * List of result names to assign the matched text to.
	 * @var string
	 */
	private $assignCallbacks = [];

	private function resolveAssignCallbacks($text)
	{
		foreach ($this->assignCallbacks as $callback) {
			$callback($text);
		}
	}

	/**
	 * After parsing, assign the matched input to the specified local variable.
	 * Only assign if successfully matched entire parent upto root.
	 *  
	 * @param type $variable
	 * @return $this
	 */
	public function assignTo(&$variable)
	{
		$this->assignCallbacks[] = function($text) use (&$variable) {
			$variable = $text;
		};
		
		return $this;
	}

}
