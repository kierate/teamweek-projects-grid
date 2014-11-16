<?php

class ProjectsModel
{
	/**
	 * @var string Where is the configuration
	 * @see processConfigFile()
	 */
	private $config_file = 'config.json';
	
	/**
	 * @var string Where will the tasks from teamweek be cached
	 */
	private $cache_file = 'projects_tasks_cache.json';

	/**
	 * @var string This is the file all requirements are stored in, will always
	 *             be there once you set a single requirement.
	 */
	private $requirements_file = 'project_requirements.json';

	/**
	 * Possible configuration options with default values.
	 * 
	 * The default values provided can be overriden from via this->config_file.
	 *
	 * The array has the following keys:
	 *    'cache_minutes'               - int How long (in minutes) to cache
	 *                                        the data from the teamweek API
	 *    'hours_per_day'               - int|float How many hours are there
	 *                                              in the work day
	 *    'non_work_days'               - array Which days of the week are not
	 *                                          workdays
	 *    'holiday_projects'            - array|null Are holidays recorded here
	 *                                               (if an array should have
	 *                                               2 elements)
	 *    'teamweek_api_base_url'       - string The base URL for all teamweek
	 *                                           API calls
	 *    'teamweek_account_id'         - string ID for your teamweek API
	 *                                           account
	 *    'teamweek_account_auth_token' - string Token for your teamweek API
	 *                                           account
	 *    'teamweek_account_base_url'   - string The base URL for teamweek
	 * 
	 * @var array Configuration options
	 */
	private $options = array(
			'cache_minutes'               => 60,
			'hours_per_day'               => 8,
			'non_work_days'               => array('Saturday', 'Sunday'),
			'holiday_projects'            => array('Bank Holiday' => 'Bank Holiday(s)', 'Holiday' => 'Holiday(s)'),
			'teamweek_api_base_url'       => "https://teamweek.com/api/v2/",
			'teamweek_account_id'         => null,
			'teamweek_account_auth_token' => null,
			'teamweek_account_base_url'   => "https://teamweek.com/"
		);

	/**
	 * @var array Array for storing teamweek users
	 * @see getUsers()
	 */
	private $users = null;

	/**
	 * @var array Array for storing project requirements
	 * @see getProjectRequirements()
	 */
	private $project_requirements = null;

	/**
	 * @var array Array for storing metadata about projects
	 * @see getProjectMetadata()
	 */
	private $project_metadata = null;

	/**
	 * @var array Array for storing the entire set of data to be displayed in
	 *            the projects table
	 * @see getProjectTableData()
	 */
	private $project_table_data = null;

	/**
	 * @var array Array for storing any tasks that do not have a project
	 * @see getProjectTableData()
	 */
	private $projectless_tasks = null;

	/**
	 * @var float The total of days allocated in teamweek
	 * @see calculateTotalsAndUtilisation()
	 */
	private $total_allocated = 0;

	/**
	 * @var int The total of days available for scheduling
	 * @see calculateTotalAvailable()
	 */
	private $total_available = 0;

	/**
	 * @var float The utilisation total (allocated / available)
	 * @see calculateTotalUtilisation()
	 */
	private $total_utilisation = 0;

	/**
	 * @var boolean Is cache enabled or not
	 * @see bypassCache()
	 * @see isCacheBypassed()
	 */
	private $use_cache = true;

	/**
	 * @var array Details of the bank/public holidays
	 * @see processHolidayConfiguration()
	 * @see processTaskAllocation()
	 * @see getBankHolidayInfo()
	 */
	private $bank_holidays = array(
			'project_name' => null,
			'info_display_name' => null,
			'total_allocated' => null
		);

	/**
	 * @var array Details of the holidays/annual leave
	 * @see processHolidayConfiguration()
	 * @see processTaskAllocation()
	 * @see getHolidayInfo()
	 */
	private $holidays = array(
			'project_name' => null,
			'info_display_name' => null,
			'total_allocated' => null
		);

	/**
	 * Constructor sets up internal variables and config.
	 *
	 * Default config for most of the variables is provided int $this->options.
	 * The teamweek API details are not provided in the defaults and have to be
	 * provided in the config.json file - this can also override any of the
	 * default values.
	 *
	 * For holidays/annual leave and bank/national holidays the config names
	 * are taken and stored in $this->bank_holidays and $this->holidays.
	 * If the "holiday_projects" option is provided as key+value pairs then the
	 * key is the project name and the value is a display name for the summary.
	 * If it's passed in as an indexed array then the value is used as the
	 * project name as well as ther display name.
	 * 
	 * @param AppController $controller the controller is passed in to allow
	 *                                  access to a few request related details
	 */
	public function __construct(AppController $controller)
	{
		$this->controller = $controller;

		$this->processConfig();
	}

	/**
	 * Process the configuration options
	 *
	 * This checks the configuration file, the minimum required options and
	 * processes the holiday configuration.
	 *
	 */
	public function processConfig()
	{
		$this->processConfigFile();
		$this->checkRequiredConfiguration();
		$this->processHolidayConfiguration();
	}

	/**
	 * Process the options in the configuration file
	 *
	 * If the config file does not exist then nothing happens.
	 * Only options defined in $this->options are allowed to be configured in
	 * the file. In case anything else is passed in this will throw an
	 * exception.
	 *
	 * @throws Exception In case you pass in an unknown config key
	 */
	public function processConfigFile()
	{
		//fill in the options from the config file
		if (file_exists($this->config_file)) {
			$config = json_decode(file_get_contents($this->config_file), true);

			if (!isset($config)) {
				throw new Exception("Your '$this->config_file' file appears to be broken/incorrect");
			}

			foreach ($config as $key => $value) {
				if (array_key_exists($key, $this->options)) {
					$this->options[$key] = $value;
				} else {
					throw new Exception("Unknown config item '$key' provided in '$this->config_file'");
				}
			}
		}
	}

	/**
	 * Check if the minimum configuration is provided.
	 *
	 * Currently only the teamweek account details are listed here as they do
	 * not have default values. In case they are not provided this will throw
	 * an exception.
	 *
	 * @throws Exception In case some of the config is missing
	 */
	public function checkRequiredConfiguration()
	{
		//make sure the teamweek API details are provided
		if (!isset($this->options['teamweek_account_id']) ||
			 empty($this->options['teamweek_account_id']) ||
			!isset($this->options['teamweek_account_auth_token']) ||
			 empty($this->options['teamweek_account_auth_token'])) {
			throw new Exception("Teamweek account details missing. Please set up 'teamweek_account_id' and 'teamweek_account_auth_token' in '$this->config_file'");
		}
	}

	/**
	 * Process the holiday configuration options
	 *
	 * The option defined in the config has to be a 2 element array - if it's
	 * not then this will throw an exception.
	 *
	 * If the config for this is provided with 2 elements then the first
	 * element is the bank/national holidays and the second element is the
	 * regular holidays/annual leave.
	 *
	 * For each of the 2 elements if the array key is provided it is
	 * considered the project name, otherwise the value is used as project and
	 * display name.
	 *
	 * @throws Exception In case holiday_projects is an array but of wrong size
	 */
	public function processHolidayConfiguration()
	{
		//set up the holiday handling if defined in the options
		if (isset($this->options['holiday_projects'])) {
			//the option needs to be a 2 element array
			if (count($this->options['holiday_projects']) != 2) {
				throw new Exception("Configuration for holiday projects is incorrect. Should contain two elements (1. bank/public/national holidays and 2. annual leave) or be set to null in '$this->config_file'");
			}

			$project_names = array_keys($this->options['holiday_projects']);
			$display_names = array_values($this->options['holiday_projects']);

			//first element is the bank/national holidays
			$this->bank_holidays['project_name'] = $this->bank_holidays['info_display_name'] = $display_names[0];
			if (is_string($project_names[0])) {
				$this->bank_holidays['project_name'] = $project_names[0];
			}

			//second element is the regular holidays/annual leave
			$this->holidays['project_name'] = $this->holidays['info_display_name'] = $display_names[1];
			if (is_string($project_names[1])) {
				$this->holidays['project_name'] = $project_names[1];
			}
		}
	}

	/**
	 * Get project requirements
	 *
	 * The project requirements returned are for each week within each project.
	 *
	 * @return array All project requirements that have been
	 *               saved through indexAction
	 */
	public function getProjectRequirements()
	{
		//return if internally cached
		if (isset($this->project_requirements)) {
			return $this->project_requirements;
		}

		//grab the requirements from the requirements store
		$this->project_requirements = $this->getArrayFromJsonFile($this->requirements_file);

		return $this->project_requirements;
	}

	/**
	 * Save a requirement for a given project in a specified week
	 *
	 * Currently the requirements are stored in a simple json file.
	 *
	 * @param string $project_id The ID of the project from teamweek
	 *                           (this is an int at the moment)
	 * @param string $week The starting date for the week in YYY-MM-DD format
	 * @param int|float $requirement The requirement that is being set for the
	 *                               project in the specified week
	 */
	public function storeProjectRequirement($project_id, $week, $requirement)
	{
		//populate the internal variable
		$this->getProjectRequirements();

		//update the content
		$this->project_requirements[$project_id][$week] = trim($requirement);

		//save all requirements back to the requirements store
		file_put_contents($this->requirements_file, json_encode($this->project_requirements));
	}

	/**
	 * Call the API as JSON, get the results as an array
	 *
	 */
	public function callTeamweekApiMethod($method, $params = array())
	{
		$method .= ".json";
		$params['auth_token'] = $this->options['teamweek_account_auth_token'];

		$url = $this->options['teamweek_api_base_url'] .
			   $this->options['teamweek_account_id'] . "/" . 
			   $method . "?" . http_build_query($params);

		$response = @file_get_contents($url);

		if ($response === false) {
			return array();
		}

		return json_decode($response, true);
	}

	/**
	 * Get teamweek account base URL
	 *
	 * The base URL is used when linking to the project edit page and to
	 * generate the PDF.
	 *
	 * @return string The base for the teamweek account
	 */
	public function getAccountBaseUrl()
	{
		return $this->options['teamweek_account_base_url'];
	}

	/**
	 * Disable the cache
	 *
	 * This is done when you force reload - all data is then fetched from
	 * teamweek directly.
	 *
	 */
	public function bypassCache()
	{
		$this->use_cache = false;
	}

	/**
	 * Check if the cache is bypassed
	 *
	 * @return boolean Is cached bypassed or not
	 */
	private function isCacheBypassed()
	{
		return !$this->use_cache;
	}

	/**
	 * Return data from the cache storage
	 *
	 * This returns the data that was previously cached under $key.
	 *
	 * If caching is disabled or the cache entry has expired then this returns
	 * null.
	 * 
	 * When a cache entry is found to be expired it is removed from the
	 * storage.
	 *
	 * @see cacheData() for structure of the cache storage
	 * @return mixed Content from the cache stored under $key
	 */
	private function getDataFromCache($key = null)
	{
		//in case caching is disabled return null
		if ($this->isCacheBypassed()) {
			return null;
		}

		$data_from_cache = null;
		$refresh_required = false;

		//get the content of the entire cache storage
		$cache = $this->getArrayFromJsonFile($this->cache_file);

		//check if the $key was cached previously
		if (isset($key) && isset($cache[$key])) {
			$data = $cache[$key];

			switch ($key) {
				case 'users': //users have a simple cache structure
					foreach ($data as $created => $users) {
						if (strtotime($created) >= (time() - $this->options['cache_minutes'] * 60)) {
							//found an entry that is still active
							$data_from_cache = $users;
							break;
						} else {
							//stale cache entry so can be dropped
							unset($cache[$key][$created]);
							$refresh_required = true;
						}
					}
					break;
				
				default: //all other cached data is based on the start date and week count
					$start_length_key = $this->controller->getScheduleStartingDay() . '__' .
										$this->controller->getWeeksToShowCount();
					if (array_key_exists($start_length_key, $data)) {
						if (strtotime($data[$start_length_key]['created']) >= (time() - $this->options['cache_minutes'] * 60)) {
							//found an entry that is still active
							$data_from_cache = $data[$start_length_key]['content'];
						} else {
							//stale cache entry so can be dropped
							unset($cache[$key][$start_length_key]);
							$refresh_required = true;
						}
					}
					break;
			}
		}

		//need to refresh the cache storage to remove expired keys
		if ($refresh_required) {
			$this->cacheData(null, $cache);
		}

		return $data_from_cache;
	}


	/**
	 * Cache the data provided.
	 *
	 * Cache an array provided in $data_to_cache under a $key. The cache
	 * entry is stored along with the creation date and will be expired
	 * after 60 minutes by default (options['cache_minutes']).
	 *
	 * Data is cached in the following format:
	 *	 key => array(
	 *			'start-date__duration' => array(
	 *					'created' => 'YYYY-MM-DD HH:MM:SS',
	 *					'content' => string|array
	 *				)
	 *		)
	 *
	 * Users have a simpler format for the cache:
	 *	 'users' => array(
	 *			'YYYY-MM-DD HH:MM:SS' => array(...) //created => users
	 *		)
	 *
	 * @param string|null $key The key where the data will be cached under
	 * @param array|string $data_to_cache Content to cache under $key
	 */
	private function cacheData($key, $data_to_cache)
	{
		if (isset($key)) {
			$cache = $this->getArrayFromJsonFile($this->cache_file);

			switch ($key) {
				case 'users': //users have a simple cache structure
					$cache[$key][date('Y-m-d H:i:s')] = $data_to_cache;
					break;
				
				default: //all other cached data is based on the start date and week count
					$start_length_key = $this->controller->getScheduleStartingDay() . '__' .
										$this->controller->getWeeksToShowCount();
					$cache[$key][$start_length_key] = array(
														'created' => date('Y-m-d H:i:s'),
														'content' => $data_to_cache,
														);
					break;
			}

			$data_to_cache = $cache;
		}

		file_put_contents($this->cache_file, json_encode($data_to_cache));
	}

	/**
	 * Get an array from a file with JSON data
	 * 
	 * If the file exists it's content is returned as an array (representing
	 * the JSON data inside it).
	 * If the file does not exist then this returns an empty array.
	 * 
	 * @param string $file The location for the file to read
	 * @return array The data from the file (or empty array of the file
	 *               doesn't exist)
	 */
	private function getArrayFromJsonFile($file)
	{
		$array = array();

		if (file_exists($file)) {
			$array = json_decode(file_get_contents($file), true);
		}		

		return $array;
	}

	/**
	 * Get teamweek tasks for selected date range
	 *
	 * This calls the teamweek API everytime and gets all tasks from the period
	 * displayed in the projects table. Tasks are returned across all members
	 * of the team.
	 *
	 * @return array Array with the tasks from teamweek
	 */
	private function getTasks()
	{
		$tasks = $this->callTeamweekApiMethod('tasks',
												array(
													'range_start' => $this->controller->getScheduleStartingDay(),
													'days' => $this->controller->getWeeksToShowCount()*7
												)
											);
		
		//filter out tasks that we done by inactive users
		foreach ($tasks as $task_id => $task) {
			if (!in_array($task['user_id'], array_keys($this->getUsers()))) {
				unset($tasks[$task_id]);
			}
		}

		return $tasks;
	}

	/**
	 * Get teamweek users
	 *
	 * This is taken from the API or the cache if available.
	 * For the moment this is just an array with the ID and name of the user.
	 *
	 * @return array Members of the team on teamweek
	 */
	public function getUsers()
	{
		//check if cached internally
		if (isset($this->users)) {
			return $this->users;
		}

		//try the cache and return from there if found
		$this->users = $this->getDataFromCache('users');
		if (isset($this->users)) {
			return $this->users;
		}

		//grab users from the API and convert into a different array layout
		$api_users = $this->callTeamweekApiMethod('users');
		$this->users = array();
		foreach ($api_users as $user) {
			$this->users[$user['id']] = array(
						'name' => $user['name'],
						//'avatar_background' => $user['avatar_background'],
					);
		}

		//cache under the users key (see cacheData() for details)
		$this->cacheData('users', $this->users);

		return $this->users;
	}

	/**
	 * Get metadata about the teamweek projects
	 *
	 * This is taken from the API or the cache if available.
	 * For each project we store the ID, name, colour, status and client info.
	 *
	 * @return array Basic details about each project we have on teamweek
	 */
	public function getProjectMetadata()
	{
		//check if cached internally
		if (isset($this->project_metadata)) {
			return $this->project_metadata;
		}

		//try the cache and return from there if found
		$this->project_metadata = $this->getDataFromCache('project_metadata');
		if (isset($this->project_metadata)) {
			return $this->project_metadata;
		}

		$this->project_metadata = array();

		//grab projects from the API and convert into a different array layout
		$projects = $this->callTeamweekApiMethod('projects', array('filter' => 'all'));
		foreach ($projects as $project) {
			$this->project_metadata[$project['id']] = array(
					'name' => $project['name'],
					'color' => $project['color'],
					'client_id' => $project['client_id'],
					'client_name' => $project['client_name'],
					'active' => $project['active'],
				);
		}

		//cache under the project_metadata key
		$this->cacheData('project_metadata', $this->project_metadata);

		return $this->project_metadata;
	}

	/**
	 * Get all data for the project table
	 *
	 * This is the main function that pulls data together. Tasks from teamweek
	 * and the requirements defined by the user on here are put together into
	 * a single array and then totals/utilisation is calculated on top of that.
	 * All data in the array is organised per project, per week.
	 *
	 * Data is cached and taken from the cache if already there.
	 *
	 * @return array Requirements and allocation for each project across all weeks
	 */
	public function getProjectTableData()
	{
		//check if cached internally
		if (isset($this->project_table_data)) {
			return $this->project_table_data;
		}

		//try the cache and return from there if found
		$this->project_table_data = $this->getDataFromCache('project_table_data');
		if (isset($this->project_table_data)) {
			$this->calculateTotalsAndUtilisation();
			return $this->project_table_data;
		}

		//start from an empty array
		$this->project_table_data = array();

		//grab teamweek tasks and the stored requirements,
		//put it all together and calucluate the totals
		$this->setupWeeklyAllocationAndRequirements();
		$this->processTaskAllocation();
		$this->processProjectRequirements();
		$this->calculateTotalsAndUtilisation();

		//cache under the project_table_data key
		$this->cacheData('project_table_data', $this->project_table_data);

		return $this->project_table_data;
	}

	/**
	 * Initiate each project with empty allocation/requirements for each week
	 * in the selected period
	 *
	 */
	public function setupWeeklyAllocationAndRequirements()
	{
		foreach (array_keys($this->getProjectMetadata()) as $project_id) {
			if (!isset($this->project_table_data[$project_id])) {
				$this->project_table_data[$project_id] = $this->getEmptyWeeklyAllocationAndRequirements();
			}
		}
	}

	/**
	 * Get an array with empty allocation/requirements for each week in the
	 * selected period.
	 *
	 * @return array Array with empty allocation/requirements for each week
	 */
	private function getEmptyWeeklyAllocationAndRequirements()
	{
		$allocated_required = array();
		
		foreach ($this->controller->getWeekDates() as $week) {
			$allocated_required[$week['start']]['allocated'] = 0;
			$allocated_required[$week['start']]['required'] = 0;
		}

		return $allocated_required;
	}

	/**
	 * Get a list of all tasks that were not assigned to a project
	 *
	 * @return array All tasks without a project
	 */
	public function getProjectlessTasks()
	{
		if (!isset($this->projectless_tasks) && isset($this->project_table_data)) {
			$this->projectless_tasks = $this->getDataFromCache('projectless_tasks');
		}
		
		return $this->projectless_tasks;
	}

	/**
	 * Get details of the bank holiday project.
	 *
	 * @return array Content of the 'bank_holidays' property
	 */
	public function getBankHolidayInfo()
	{
		if (!isset($this->options['holiday_projects'])) {
			return null;
		}

		if (!isset($this->bank_holidays['total_allocated']) && isset($this->project_table_data)) {
			$this->bank_holidays['total_allocated'] = $this->getDataFromCache('bank_holidays_allocated');
		}
		
		return $this->bank_holidays;
	}

	/**
	 * Get details of the holiday project.
	 *
	 * @return array Content of the 'holidays' property
	 */
	public function getHolidayInfo()
	{
		if (!isset($this->options['holiday_projects'])) {
			return null;
		}

		if (!isset($this->holidays['total_allocated']) && isset($this->project_table_data)) {
			$this->holidays['total_allocated'] = $this->getDataFromCache('holidays_allocated');
		}
		
		return $this->holidays;
	}

	/**
	 * Process all teamweek tasks an calculate the allocation.
	 *
	 * Go though all tasks and calculate how much time is allocated in in each
	 * of the projects for each of the weeks currently in the view.
	 *
	 * This also stores tasks without a project and caluclates total holiday
	 * allocation (if enabled).
	 * 
	 */
	private function processTaskAllocation()
	{
		$this->projectless_tasks = array();
		
		if (isset($this->options['holiday_projects'])) {
			$this->bank_holidays['total_allocated'] = 0;
			$this->holidays['total_allocated'] = 0;
		}

		foreach ($this->getTasks() as $task) {
			//tasks with no dates are ignored
			if (empty($task['start_date']) && empty($task['end_date'])) {
				continue;
			}

			//store tasks without a project separately
			if (!isset($task['project'])) {
				$this->projectless_tasks[] = array(
						'start_date' => $task['start_date'],
						'end_date' => $task['end_date'],
						'comment' => $task['comment'],
					);
				continue;
			}

			//project we don't know about yet
			$project_id = $task['project']['id'];
			if (!isset($this->project_table_data[$project_id])) {
				$this->project_table_data[$project_id] = $this->getEmptyWeeklyAllocationAndRequirements();
			}

			//calculate the time (in days) that was allocated to this task.
			$start_date = $task['start_date'];
			$end_date = $task['end_date'];
			if ($start_date < $this->controller->getScheduleStartingDay()) {
				$start_date = $this->controller->getScheduleStartingDay();
			}
			$days = round(abs(strtotime($end_date) - strtotime($start_date)) / 86400) + 1; //number of days

			//for each of the days count which week they belong to
			for ($i = 0; $i < $days; $i++) {
				$day_of_week = date('l', strtotime($start_date . "+$i days"));

				//non work days (e.g. weekends) are not included
				if (!in_array($day_of_week, $this->options['non_work_days'])) {
					$date = date('Y-m-d', strtotime($start_date . "+$i days"));

					//count the date if it fits into the week
					foreach ($this->controller->getWeekDates() as $week) {
						if ($date >= $week['start'] && $date <= $week['end']) {
							//each day gets the equal amount allocated based on the daily length
							$this->project_table_data[$project_id][$week['start']]['allocated'] += 1 * $this->getTaskLengthPerDay($task);
						}
					}

					//calculate holidays here to make sure non_work_days are excluded
					if (isset($this->options['holiday_projects'])) {
						if ($task['project']['name'] == $this->bank_holidays['project_name']) {
							$this->bank_holidays['total_allocated']++;
						} elseif ($task['project']['name'] == $this->holidays['project_name']) {
							$this->holidays['total_allocated']++;
						}
					}
				}
			}
		}

		//cache the tasks without the projects and the holidays allocation
		$this->cacheData('projectless_tasks', $this->projectless_tasks);
		$this->cacheData('bank_holidays_allocated', $this->bank_holidays['total_allocated']);
		$this->cacheData('holidays_allocated', $this->holidays['total_allocated']);
	}

	/**
	 * Get a duration of a task in days.
	 *
	 * The duration is calculated based on the 'estimated_hours' field for the
	 * task and the 'hours_per_day' option.
	 *
	 * @param array $task The task as retieved from teamweek
	 * @return float The duration
	 */
	private function getTaskLengthPerDay($task)
	{
		$length_per_day = 1;
		
		if (!empty($task['estimated_hours']) && $task['estimated_hours'] != '0.0') {
			$length_per_day = $task['estimated_hours'] / $this->options['hours_per_day'];
		}

		return $length_per_day;
	}

	/**
	 * Populate project table data with the requirements retrieved from the
	 * requirements store.
	 *
	 * @see getProjectRequirements()
	 */
	private function processProjectRequirements()
	{
		foreach ($this->getProjectRequirements() as $project_id => $weekly_requirements) {
			if (array_key_exists($project_id, $this->getProjectMetadata())) {
				if (!isset($this->project_table_data[$project_id])) {
					$this->project_table_data[$project_id] = $this->getEmptyWeeklyAllocationAndRequirements();
				}

				foreach ($weekly_requirements as $week => $requirement) {
					if (array_key_exists($week, $this->project_table_data[$project_id])) { //for weeks that are in the current view
						$this->project_table_data[$project_id][$week]['required'] = $requirement;
					}
				}
			}
		}
	}

	/**
	 * Calculate project totals plus allocation, available days and utilisation
	 * across the entire period.
	 *
	 * For each project this calculates the amount of allocated days (this is
	 * from teamweek), amount of required days (this is stored here) and the
	 * utilisation (former divided by the later).
	 * 
	 */
	private function calculateTotalsAndUtilisation()
	{
		foreach ($this->project_table_data as $project_id => $weekly_data) {
			$allocated = 0;
			$required = 0;
			foreach ($weekly_data as $week => $data) {
				if ($week  != "totals") {
					$allocated += $data['allocated'];
					$required += $data['required'];
				}
			}
			
			$utilisation = 0;
			if ($required == 0) {
				$utilisation = "N/A";
			} else {
				$utilisation = round($allocated/$required * 100, 1); //this represents a percentage value
			}
			$this->project_table_data[$project_id]['totals'] = array(
					'allocated' => $allocated,
					'required' => $required,
					'utilisation' => $utilisation
				);
			
			$this->total_allocated += $allocated;
		}

		$this->calculateTotalAvailable();
		$this->calculateTotalUtilisation();
	}

	/**
	 * Calculate the total of available days in the current period.
	 * 
	 * This is calculated as user count times week count times how many working
	 * days do we have per week.
	 * 
	 */
	private function calculateTotalAvailable()
	{
		$working_days_count = $this->controller->getWeeksToShowCount() * (7 - count($this->options['non_work_days']));
		$this->total_available = count($this->getUsers()) * $working_days_count;
	}

	/**
	 * Calculate the utilisation total.
	 * 
	 * This is calculated as the allocated total divided by the total of
	 * available days.
	 * 
	 */
	private function calculateTotalUtilisation()
	{
		if ($this->getTotalAvailable() == 0) {
			$this->total_utilisation = 0;
		} else {
			$this->total_utilisation = round($this->getTotalAllocated() / $this->getTotalAvailable() * 100, 1);
		}
	}

	/**
	 * Get the allocated total.
	 * 
	 * This is how much was scheduled in teamweek. This is a float rounded with
	 * one decimal place precision.
	 * 
	 * @return float Allocated total
	 */
	public function getTotalAllocated()
	{
		return $this->total_allocated;
	}

	/**
	 * Get the total of available days in the current period.
	 * 
	 * This is based on the number of all users and the number of working days
	 * in the period.
	 * 
	 * @return int Total of available days for the current period
	 */
	public function getTotalAvailable()
	{
		return $this->total_available;
	}

	/**
	 * Get the utilisation total.
	 * 
	 * This is how much was scheduled/allocated vs the total amount of
	 * available days in the period. This is a float rounded with one decimal
	 * place precision.
	 * 
	 * @return float Utilisation total
	 */
	public function getTotalUtilisation()
	{
		return $this->total_utilisation;
	}
	
}