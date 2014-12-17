<?php
namespace Rocketeer\Console\Commands\Development;

use Exception;
use Illuminate\Filesystem\FileNotFoundException;
use Rocketeer\Abstracts\AbstractCommand;
use Rocketeer\Console\SelfUpdater;
use Symfony\Component\Console\Input\InputOption;

/**
 * Self update command for Rocketeer
 * Largely inspired by Composer's
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class SelfUpdateCommand extends AbstractCommand
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'selfupdate';

	/**
	 * @type string
	 */
	protected $description = 'Update Rocketeer to the latest version';

	/**
	 * Run the tasks
	 *
	 * @return integer|null
	 */
	public function fire()
	{
		$localFilename = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
		$updater       = new SelfUpdater($this->laravel, $localFilename, $this->option('version'));

		try {
			$updater->update();
		} catch (FileNotFoundException $exception) {
			return $this->error('Unable to find archive: '.$exception->getMessage());
		} catch (Exception $exception) {
			return $this->error('An error occured while updated: '.$exception->getMessage());
		}
	}

	/**
	 * Write a string as error output.
	 *
	 * @param string $string
	 *
	 * @return integer
	 */
	public function error($string)
	{
		parent::error($string);

		return 1;
	}

	/**
	 * @return array
	 */
	public function getOptions()
	{
		return array(
			['version', 'V', InputOption::VALUE_REQUIRED, 'The version to update to'],
		);
	}
}
