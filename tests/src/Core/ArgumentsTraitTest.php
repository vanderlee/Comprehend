<?php

use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\parser\terminal\Any;
use \vanderlee\comprehend\parser\structure\Choice;

class ArgumentsTraitStub {
	use ArgumentsTrait {
		ArgumentsTrait::getArgument as traitGetArgument;
		ArgumentsTrait::getArguments as traitGetArguments;
	}
	
	public static function getArgument($argument) {
		return self::traitGetArgument($argument);
	}
	
	public static function getArguments($arguments) {
		return self::traitGetArguments($arguments);
	}
}

/**
 * Description of ArgumentsTraitTest
 *
 * @author Martijn
 */
class ArgumentsTraitTest extends TestCase {

	/**
	 * @covers ArgumentsTrait::getArgument
	 */
	public function testGetArgumentInvalidType()
	{
		$this->expectExceptionMessage('Invalid argument type `boolean`');
		ArgumentsTraitStub::getArgument(true);
	}

	/**
	 * @covers ArgumentsTrait::getArgument
	 */
	public function testGetArgumentEmpty()
	{
		$this->expectExceptionMessage('Empty argument');
		ArgumentsTraitStub::getArgument('');
	}
	
	/**
	 * @covers ArgumentsTrait::getArgument
	 */
	public function testGetArgumentType()
	{
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument('x'));
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument(0x50));
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArgument('ab'));
		$this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArgument(new Any));
		$this->assertInstanceOf(Choice::class, ArgumentsTraitStub::getArgument(['a', 'b']));
	}

	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testGetArgumentsInvalidType()
	{
		$this->expectExceptionMessage('Invalid argument type `boolean`');
		ArgumentsTraitStub::getArguments(['a', true]);
	}

	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testGetArgumentsEmpty()
	{
		$this->expectExceptionMessage('Empty argument');
		ArgumentsTraitStub::getArguments(['a', '']);
	}
	
	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testGetArgumentsEmptyArray()
	{
		$this->expectExceptionMessage('Empty array argument');
		ArgumentsTraitStub::getArguments([[]]);
	}
	
	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testGetArgumentsType()
	{
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments(['x', 'y'])[0]);
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([0x50, 0x51])[0]);
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments(['ab', 'cd'])[0]);
		$this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArguments([new Any, 'a'])[0]);
		$this->assertInstanceOf(Choice::class, ArgumentsTraitStub::getArguments([['a', 'b'], 'c'])[0]);
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([['a'], 'bc'])[0]);
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments([['ab'], 'c'])[0]);
	}

}
