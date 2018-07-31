<?php

use \vanderlee\comprehend\core\ArgumentsTrait;
use \vanderlee\comprehend\parser\terminal\Char;
use \vanderlee\comprehend\parser\terminal\Text;
use \vanderlee\comprehend\parser\terminal\Any;
use \vanderlee\comprehend\parser\structure\Choice;
use \vanderlee\comprehend\parser\structure\Sequence;

class ArgumentsTraitStub {
	use ArgumentsTrait {
		ArgumentsTrait::getArgument as traitgetArgument;
		ArgumentsTrait::getArguments as traitgetArguments;
	}
	
	public static function getArgument($argument) {
		return self::traitgetArgument($argument);
	}
	
	public static function getArguments($arguments) {
		return self::traitgetArguments($arguments);
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
	public function testgetArgumentInvalidType()
	{
		$this->expectExceptionMessage('Invalid argument type `boolean`');
		ArgumentsTraitStub::getArgument(true);
	}

	/**
	 * @covers ArgumentsTrait::getArgument
	 */
	public function testgetArgumentEmpty()
	{
		$this->expectExceptionMessage('Empty argument');
		ArgumentsTraitStub::getArgument('');
	}
	
	/**
	 * @covers ArgumentsTrait::getArgument
	 */
	public function testgetArgumentType()
	{
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument('x'));
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArgument(0x50));
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArgument('ab'));
		$this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArgument(new Any));
		$this->assertInstanceOf(Sequence::class, ArgumentsTraitStub::getArgument(['a', 'b']));
	}

	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testgetArgumentsInvalidType()
	{
		$this->expectExceptionMessage('Invalid argument type `boolean`');
		ArgumentsTraitStub::getArguments(['a', true]);
	}

	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testgetArgumentsEmpty()
	{
		$this->expectExceptionMessage('Empty argument');
		ArgumentsTraitStub::getArguments(['a', '']);
	}
	
	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testgetArgumentsEmptyArray()
	{
		$this->expectExceptionMessage('Empty array argument');
		ArgumentsTraitStub::getArguments([[]]);
	}
	
	/**
	 * @covers ArgumentsTrait::getArguments
	 */
	public function testgetArgumentsType()
	{
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments(['x', 'y'])[0]);
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([0x50, 0x51])[0]);
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments(['ab', 'cd'])[0]);
		$this->assertInstanceOf(Any::class, ArgumentsTraitStub::getArguments([new Any, 'a'])[0]);
		$this->assertInstanceOf(Sequence::class, ArgumentsTraitStub::getArguments([['a', 'b'], 'c'])[0]);
		$this->assertInstanceOf(Char::class, ArgumentsTraitStub::getArguments([['a'], 'bc'])[0]);
		$this->assertInstanceOf(Text::class, ArgumentsTraitStub::getArguments([['ab'], 'c'])[0]);
	}

}
