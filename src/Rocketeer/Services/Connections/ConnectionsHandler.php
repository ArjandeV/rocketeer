<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Services\Connections;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Rocketeer\Exceptions\ConnectionException;
use Rocketeer\Traits\HasLocator;

/**
 * Handles, get and return, the various connections/stages
 * and their credentials
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class ConnectionsHandler
{
	use HasLocator;

	/**
	 * The current handle
	 *
	 * @type string
	 */
	protected $handle;

	/**
	 * The current stage
	 *
	 * @var string
	 */
	protected $stage;

	/**
	 * The current server
	 *
	 * @type integer
	 */
	protected $currentServer = 0;

	/**
	 * The connections to use
	 *
	 * @var array|null
	 */
	protected $connections;

	/**
	 * The current connection
	 *
	 * @var string|null
	 */
	protected $connection;

	/**
	 * Build the current connection's handle
	 *
	 * @param string|null  $connection
	 * @param integer|null $server
	 * @param string|null  $stage
	 *
	 * @return string
	 */
	public function getHandle($connection = null, $server = null, $stage = null)
	{
		if ($this->handle) {
			return $this->handle;
		}

		// Get identifiers
		$connection = $connection ?: $this->getConnection();
		$server     = $server ?: $this->getServer();
		$stage      = $stage ?: $this->getStage();

		// Filter values
		$handle = [$connection, $server, $stage];
		if ($this->isMultiserver($connection)) {
			$handle = array_filter($handle, function ($value) {
				return $value !== null;
			});
		} else {
			$handle = array_filter($handle);
		}

		// Concatenate
		$handle       = implode('/', $handle);
		$this->handle = $handle;

		return $handle;
	}

	//////////////////////////////////////////////////////////////////////
	////////////////////////////// SERVERS ///////////////////////////////
	//////////////////////////////////////////////////////////////////////

	/**
	 * @return int
	 */
	public function getServer()
	{
		return $this->currentServer;
	}

	/**
	 * Check if a connection is multiserver or not
	 *
	 * @param string $connection
	 *
	 * @return boolean
	 */
	public function isMultiserver($connection)
	{
		return count($this->getConnectionCredentials($connection)) > 1;
	}

	////////////////////////////////////////////////////////////////////
	//////////////////////////////// STAGES ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the current stage
	 *
	 * @return string
	 */
	public function getStage()
	{
		return $this->stage;
	}

	/**
	 * Set the stage Tasks will execute on
	 *
	 * @param string|null $stage
	 */
	public function setStage($stage)
	{
		if ($stage === $this->stage) {
			return;
		}

		$this->stage  = $stage;
		$this->handle = null;

		// If we do have a stage, cleanup previous events
		if ($stage) {
			$this->tasks->registerConfiguredEvents();
		}
	}

	/**
	 * Get the various stages provided by the User
	 *
	 * @return array
	 */
	public function getStages()
	{
		return (array) $this->rocketeer->getOption('stages.stages');
	}

	////////////////////////////////////////////////////////////////////
	///////////////////////////// APPLICATION //////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Whether the repository used is using SSH or HTTPS
	 *
	 * @return boolean
	 */
	public function needsCredentials()
	{
		return Str::contains($this->getRepositoryEndpoint(), 'https://');
	}

	/**
	 * Get the available connections
	 *
	 * @return string[][]|string[]
	 */
	public function getAvailableConnections()
	{
		// Fetch stored credentials
		$storage = $this->localStorage->get('connections');
		$storage = $this->unifyMultiserversDeclarations($storage);

		// Merge with defaults from config file
		$configuration = $this->config->get('rocketeer::connections');
		$configuration = $this->unifyMultiserversDeclarations($configuration);

		// Fetch from remote file
		$remote = $this->config->get('remote.connections');
		$remote = $this->unifyMultiserversDeclarations($remote);

		// Merge configurations
		$connections = array_replace_recursive($remote, $configuration, $storage);

		return $connections;
	}

	/**
	 * Check if a connection has credentials related to it
	 *
	 * @param string $connection
	 *
	 * @return boolean
	 */
	public function isValidConnection($connection)
	{
		$available = (array) $this->getAvailableConnections();

		return (bool) Arr::get($available, $connection.'.servers');
	}

	/**
	 * Get the connection in use
	 *
	 * @return string[]
	 */
	public function getConnections()
	{
		// Get cached resolved connections
		if ($this->connections) {
			return $this->connections;
		}

		// Get all and defaults
		$connections = (array) $this->config->get('rocketeer::default');
		$default     = $this->config->get('remote.default');

		// Remove invalid connections
		$instance    = $this;
		$connections = array_filter($connections, function ($value) use ($instance) {
			return $instance->isValidConnection($value);
		});

		// Return default if no active connection(s) set
		if (empty($connections) && $default) {
			return array($default);
		}

		// Set current connection as default
		$this->connections = $connections;

		return $connections;
	}

	/**
	 * Set the active connections
	 *
	 * @param string|string[] $connections
	 *
	 * @throws ConnectionException
	 */
	public function setConnections($connections)
	{
		if (!is_array($connections)) {
			$connections = explode(',', $connections);
		}

		// Sanitize and set connections
		$filtered = array_filter($connections, [$this, 'isValidConnection']);
		if (!$filtered) {
			throw new ConnectionException('Invalid connection(s): '.implode(', ', $connections));
		}

		$this->connections = $filtered;
		$this->handle      = null;
	}

	/**
	 * Get the active connection
	 *
	 * @return string
	 */
	public function getConnection()
	{
		// Get cached resolved connection
		if ($this->connection) {
			return $this->connection;
		}

		$connection       = Arr::get($this->getConnections(), 0);
		$this->connection = $connection;

		return $this->connection;
	}

	/**
	 * Set the current connection
	 *
	 * @param string $connection
	 * @param int    $server
	 */
	public function setConnection($connection, $server = 0)
	{
		if (!$this->isValidConnection($connection) || (($this->connection === $connection) && ($this->currentServer === $server))) {
			return;
		}

		// Set the connection
		$this->handle        = null;
		$this->connection    = $connection;
		$this->localStorage  = $server;
		$this->currentServer = $server;

		// Update events
		$this->tasks->registerConfiguredEvents();
	}

	/**
	 * Get the credentials for a particular connection
	 *
	 * @param string|null $connection
	 *
	 * @return string[][]
	 */
	public function getConnectionCredentials($connection = null)
	{
		$connection = $connection ?: $this->getConnection();
		$available  = $this->getAvailableConnections();

		// Get and filter servers
		$servers = Arr::get($available, $connection.'.servers');
		if ($this->hasCommand() && $allowed = $this->command->option('server')) {
			$allowed = explode(',', $allowed);
			$servers = array_intersect_key((array) $servers, array_flip($allowed));
		}

		return $servers;
	}

	/**
	 * Get thecredentials for as server
	 *
	 * @param string|null  $connection
	 * @param integer|null $server
	 *
	 * @return mixed
	 */
	public function getServerCredentials($connection = null, $server = null)
	{
		$connection = $this->getConnectionCredentials($connection);
		$server     = $server !== null ? $server : $this->currentServer;

		return Arr::get($connection, $server);
	}

	/**
	 * Sync Rocketeer's credentials with Laravel's
	 *
	 * @param string|null $connection
	 * @param array       $credentials
	 * @param int         $server
	 */
	public function syncConnectionCredentials($connection = null, array $credentials = array(), $server = 0)
	{
		// Store credentials if any
		if ($credentials) {
			$filtered = $this->filterUnsavableCredentials($connection, $server, $credentials);
			$this->localStorage->set('connections.'.$connection.'.servers.'.$server, $filtered);

			$handle = $this->getHandle($connection, $server);
			$this->config->set('rocketeer::connections.'.$handle, $credentials);
		}

		// Get connection
		$connection  = $connection ?: $this->getConnection();
		$credentials = $credentials ?: $this->getConnectionCredentials($connection);

		$this->config->set('remote.connections.'.$connection, $credentials);
	}

	/**
	 * Filter the credentials and remove the ones that
	 * can't be saved to disk
	 *
	 * @param string  $connection
	 * @param integer $server
	 * @param array   $credentials
	 *
	 * @return string[]
	 */
	protected function filterUnsavableCredentials($connection, $server, $credentials)
	{
		$defined = $this->getServerCredentials($connection, $server);
		foreach ($credentials as $key => $value) {
			if (array_get($defined, $key) === true) {
				unset($credentials[$key]);
			}
		}

		return $credentials;
	}

	/**
	 * Flush active connection(s)
	 */
	public function disconnect()
	{
		$this->connection  = null;
		$this->connections = null;
	}

	////////////////////////////////////////////////////////////////////
	/////////////////////////// GIT REPOSITORY /////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the credentials for the repository
	 *
	 * @return array
	 */
	public function getRepositoryCredentials()
	{
		$config      = (array) $this->rocketeer->getOption('scm');
		$credentials = (array) $this->localStorage->get('credentials');

		return array_merge($config, $credentials);
	}

	/**
	 * Get the URL to the Git repository
	 *
	 * @return string
	 */
	public function getRepositoryEndpoint()
	{
		// Get credentials
		$repository = $this->getRepositoryCredentials();
		$username   = Arr::get($repository, 'username');
		$password   = Arr::get($repository, 'password');
		$repository = Arr::get($repository, 'repository');

		// Add credentials if possible
		if ($username || $password) {

			// Build credentials chain
			$credentials = $password ? $username.':'.$password : $username;
			$credentials .= '@';

			// Add them in chain
			$repository = preg_replace('#https://(.+)@#', 'https://', $repository);
			$repository = str_replace('https://', 'https://'.$credentials, $repository);
		}

		return $repository;
	}

	/**
	 * Get the repository branch to use
	 *
	 * @return string
	 */
	public function getRepositoryBranch()
	{
		// If we passed a branch, use it
		if ($branch = $this->getOption('branch')) {
			return $branch;
		}

		// Compute the fallback branch
		exec($this->scm->currentBranch(), $fallback);
		$fallback = Arr::get($fallback, 0, 'master');
		$fallback = trim($fallback);
		$branch   = $this->rocketeer->getOption('scm.branch') ?: $fallback;

		return $branch;
	}

	/**
	 * Unify a connection's declaration into the servers form
	 *
	 * @param array $connection
	 *
	 * @return array
	 */
	protected function unifyMultiserversDeclarations($connection)
	{
		$connection = (array) $connection;
		foreach ($connection as $key => $servers) {
			$servers          = Arr::get($servers, 'servers', [$servers]);
			$connection[$key] = ['servers' => array_values($servers)];
		}

		return $connection;
	}
}
