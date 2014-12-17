<?php
namespace Rocketeer\Strategies\Check;

use Mockery;
use Rocketeer\TestCases\RocketeerTestCase;

class PolyglotStrategyCheckTest extends RocketeerTestCase
{
	/**
	 * @type \Rocketeer\Strategies\Check\PolyglotStrategy
	 */
	protected $strategy;

	public function setUp()
	{
		parent::setUp();

		$this->strategy = $this->builder->buildStrategy('Check', 'Polyglot');
	}

	public function testCanCheckLanguage()
	{
		$this->mock('rocketeer.builder', 'Builder', function ($mock) {
			return $mock
				->shouldReceive('buildStrategy')->with('Check', 'Node')->andReturn($this->getDummyStrategy('Node', 'language', true))
				->shouldReceive('buildStrategy')->with('Check', 'Ruby')->andReturn($this->getDummyStrategy('Ruby', 'language', true))
				->shouldReceive('buildStrategy')->with('Check', 'Php')->andReturn($this->getDummyStrategy('Php', 'language', true));
		});

		$this->strategy->language();
	}

	public function testCanCheckMissingExtensions()
	{
		$this->mock('rocketeer.builder', 'Builder', function ($mock) {
			return $mock
				->shouldReceive('buildStrategy')->with('Check', 'Node')->andReturn($this->getDummyStrategy('Node', 'extensions', ['Node']))
				->shouldReceive('buildStrategy')->with('Check', 'Ruby')->andReturn($this->getDummyStrategy('Ruby', 'extensions', ['Ruby']))
				->shouldReceive('buildStrategy')->with('Check', 'Php')->andReturn($this->getDummyStrategy('Php', 'extensions', ['Php']));
		});

		$extensions = $this->strategy->extensions();
		$this->assertEquals(['Node', 'Php', 'Ruby'], $extensions);
	}

	/**
	 * Get a dummy strategy
	 *
	 * @param string $name
	 * @param string $method
	 * @param mixed  $result
	 *
	 * @return mixed
	 */
	protected function getDummyStrategy($name, $method, $result)
	{
		return Mockery::mock('Rocketeer\Strategies\Check\\'.$name.'Strategy')
		              ->shouldIgnoreMissing()
		              ->shouldReceive('displayStatus')->andReturnSelf()
		              ->shouldReceive('isExecutable')->once()->andReturn(true)
		              ->shouldReceive($method)->once()->andReturn($result)
		              ->mock();
	}
}
