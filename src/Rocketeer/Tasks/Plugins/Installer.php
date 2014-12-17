<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Tasks\Plugins;

use Rocketeer\Abstracts\AbstractTask;

class Installer extends AbstractTask
{
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Installs plugins';

	/**
	 * Whether to run the commands locally
	 * or on the server
	 *
	 * @type boolean
	 */
	protected $local = true;

	/**
	 * Run the task
	 *
	 * @return null
	 */
	public function execute()
	{
		// Get package and destination folder
		$package = $this->command->argument('package');
		$folder  = $this->paths->getRocketeerConfigFolder();

		$command = $this->composer()->require($package, array(
			'--working-dir' => $folder,
		));

		// Install plugin
		$this->explainer->line('Installing '.$package);
		$this->run($this->shellCommand($command));

		// Prune duplicate Rocketeer
		$this->files->deleteDirectory($folder.'/vendor/anahkiasen/rocketeer');
	}
}
