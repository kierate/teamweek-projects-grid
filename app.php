<?php

class AppController
{
	/**
	 * @var string the starting day for the period shown as 'YYYY-MM-DD'
	 * @see getScheduleStartingDay()
	 */
	private $starting_day = null;

	/**
	 * @var int How many weeks to show in the view
	 * @see getWeeksToShowCount()
	 */
	private $weeks_to_show_count = null;

	/**
	 * @var array Start/end dates for all weeks that will be shown
	 * @see getWeekDates()
	 */
	private $week_details = null;

	/**
	 * @var boolean Should fully scheduled projects should be shown or not
	 * @see getHideFullyScheduledProjects()
	 */
	private $hide_fully_scheduled_projects = null;

	/**
	 * The constructor sets the exception handler
	 *
	 */
	public function __construct()
	{
		set_exception_handler(array($this, 'handleException'));
	}

	/**
	 * The index action shows the whole page and handles the ajax post
	 *
	 */
	public function indexAction()
	{
		$view_data = array();

		require_once 'projects.php';
		$projects = new ProjectsModel($this);

		if ($this->shouldDataBeRefreshed($_POST)) {
			$projects->bypassCache();
		}

		//process the posting of a requirement
		//and respond with the latest details
		if ($this->isSubmittingRequirement($_POST) && $this->validateRequirement()) {
			$projects->storeProjectRequirement($this->request['project_id'],
											   $this->request['week'],
											   $this->request['requirement']);

			echo json_encode($projects->getProjectTableData());
			exit;
		}

		//project data
		$view_data['project_table_data'] = $projects->getProjectTableData();
		$view_data['project_metadata'] = $projects->getProjectMetadata();
		$view_data['projectless_tasks'] = $projects->getProjectlessTasks();

		//what timeframe to show
		$view_data['starting_day'] = $this->getScheduleStartingDay();
		$view_data['weeks_to_show'] = $this->getWeeksToShowCount();
		$view_data['hide_fully_scheduled_projects'] = $this->getHideFullyScheduledProjects();

		//totals for the summary section
		$view_data['total_available'] = $projects->getTotalAvailable();
		$view_data['total_allocated'] = $projects->getTotalAllocated();
		$view_data['total_utilisation'] = $projects->getTotalUtilisation();
		$view_data['users_count'] = count($projects->getUsers());

		//week start change urls
		$view_data['url_week_start_change'] = $this->getURLQueryStringWeekStartChange();
		$view_data['url_week_before'] = $this->getURLQueryStringWeekBefore();
		$view_data['url_week_after'] = $this->getURLQueryStringWeekAfter();
		$view_data['url_this_week'] = $this->getURLQueryStringThisWeek();
		$view_data['url_this_month'] = $this->getURLQueryStringThisMonth();
		//week count change urls
		$view_data['url_week_more'] = $this->getURLQueryStringWeekMore();
		$view_data['url_week_less'] = $this->getURLQueryStringWeekLess();
		$view_data['url_week_count_change'] = $this->getURLQueryStringWeekCountChange();
		//force refresh, post and pdf download urls
		$view_data['url_force_refresh_action'] = $this->getURLQueryStringForceRefreshAction();
		$view_data['url_ajax_post_requirement'] = $this->getURLForAjaxRequirementPost();
		$view_data['url_pdf_download'] = $this->getURLForPDFDownload($projects->getAccountBaseUrl());
		$view_data['url_teamweek_account_base'] = $projects->getAccountBaseUrl();
		//holiday info
		$view_data['bank_holiday_info'] = $projects->getBankHolidayInfo();
		$view_data['holiday_info'] = $projects->getHolidayInfo();

		$this->showView('index.tpl', $view_data);
	}


	/**
	 * Check if the requirement is being submitted.
	 *
	 * @return boolean is the requirement currently being submitted
	 */
	private function isSubmittingRequirement($post)
	{
		if (isset($post['action']) && $post['action'] == 'edit_project_week_requirement') {
			return true;
		}

		return false;
	}


	/**
	 * Check if the content of the posted requirement request.
	 * 
	 * The required items in the request are:
	 * - project_id - an integer
	 * - week - a string in the YYYY-DD-MM
	 * - requirement - an integrer or a float
	 *
	 * If either of them is not there or is not in the correct format then
	 * this function returns false.
	 *
	 * @return boolean true if the content of the requirement was OK;
	 *                 false if anything missing/incorrect
	 */
	private function checkRequirementRequest()
	{
		$defintion = array(
				'project_id' => FILTER_SANITIZE_NUMBER_INT,
				'week' => array(
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => array(
							'regexp' => '/^\d{4}-\d{2}-\d{2}$/'
						)
					),
				'requirement' => array(
					'filter' => FILTER_VALIDATE_REGEXP,
					'options' => array(
							'regexp' => '/^\d+(\.\d+)?$/'
						)
					),
			);

		$this->request = filter_input_array(INPUT_POST, $defintion);

		if (!isset($this->request)) {
			return false;
		}

		$this->request = array_map("trim", $this->request);

		if (!isset($this->request['project_id']) || $this->request['project_id'] == "") {
			return false;
		}

		if (empty($this->request['week'])) {
			return false;
		}

		if (!isset($this->request['requirement']) || $this->request['requirement'] == "") {
			return false;
		}

		return true;
	}


	/**
	 * Check if the posted requirement is valid.
	 * 
	 * If the requirement is not valid throw a 404, otherwise return true.
	 *
	 * @return boolean true on valid requirement; otherwise will send
	 *                 a 404 header and exit the script
	 */
	private function validateRequirement()
	{
		if (!$this->checkRequirementRequest()) {
			header("HTTP/1.0 404 Not Found");
			exit;
		}

		return true;
	}


	/**
	 * Get the starting day for the period to show.
	 *
	 * If no date is in the request then this returns the date for the Monday
	 * around the start of the month (if the 1st falls later in the week then
	 * the Monday retured will be the last one from the previous month; if the
	 * 1st falls on a weekend then the Monday returned with either be the 2nd
	 * or the 3rd of the month).
	 * 
	 * If a date is provided in the request then this function returns it
	 * directly or if it is not a Monday then it returns the date of the Monday
	 * from that week.
	 *
	 * @param string|null $week_start The starting date from the request, if
	 *                                if not passed in will be taken
	 *                                from $_REQUEST['week_start'] (GET/POST)
	 * @return string The starting day for the schedule being displayed
	 */
	public function getScheduleStartingDay($week_start = null)
	{
		if (isset($this->starting_day)) {
			return $this->starting_day;
		}

		if (!isset($week_start)) {
			$week_start = isset($_REQUEST['week_start']) ? $_REQUEST['week_start'] : null;
		}

		if (!isset($week_start)) { //default to start with the week containing the first of the month (unless that falls on a weekend)
			$first_of_the_month = date('Y-m-01');
			$dow = date('l', strtotime($first_of_the_month));
			if ($dow == "Saturday") {
				$this->starting_day = date('Y-m-03');
			} elseif ($dow == "Sunday") {
				$this->starting_day = date('Y-m-02');
			} else {
				$this->starting_day = date('Y-m-d', strtotime("$first_of_the_month -" . (date('N', strtotime($first_of_the_month))-1) . 'days'));
			}
		} else {
			$this->starting_day = $week_start;

			//correct the date to a Monday in case a wrong one is passed in
			$dow = date('l', strtotime($this->starting_day));
			if ($dow != "Monday") {
				$this->starting_day = date('Y-m-d', strtotime($this->starting_day . " -" . (date('N', strtotime($this->starting_day))-1) . 'days'));
			}
		}

		return $this->starting_day;
	}


	/**
	 * Get the count of how many weeks to show in the view.
	 *
	 * The count has to be passed in as an integer. If it's not then the
	 * $default will be used. The value is cached internally.
	 *
	 * @param string|null $week_count The week count from the request, if
	 *                                if not passed in will be taken
	 *                                from $_REQUST['week_count'] (GET/POST)
	 * @param int $week_count The default number of weeks to display. Is used
	 *                        if the count is not passed in (or is passed in
	 *                        incorrectly)
	 * @return int How many weeks to show
	 */
	public function getWeeksToShowCount($week_count = null, $default = 4)
	{
		//return if already cached internally
		if (isset($this->weeks_to_show_count)) {
			return $this->weeks_to_show_count;
		}

		if (!isset($week_count)) {
			$week_count = isset($_REQUEST['week_count']) ? $_REQUEST['week_count'] : null;
		}

		$week_count = filter_var($week_count, FILTER_VALIDATE_INT);
		$this->weeks_to_show_count = is_integer($week_count) ? $week_count : $default;

		return $this->weeks_to_show_count;
	}


	/**
	 * Get the start and end dates for all weeks in the current range.
	 *
	 * For N weeks (as retured by getWeeksToShowCount()) from the starting date
	 * (which will be a Monday) get the start and end dates in ISO format.
	 *
	 * @return array An array of week dates
	 */
	public function getWeekDates()
	{
		//return if already cached internally
		if (isset($this->week_details)) {
			return $this->week_details;
		}

		$this->week_details = array();
		for ($i = 0; $i < $this->getWeeksToShowCount(); $i++) {
			$this->week_details[] = array(
					'start' => date('Y-m-d', strtotime($this->getScheduleStartingDay() . "+$i weeks")),
					'end' => date('Y-m-d', strtotime($this->getScheduleStartingDay() . "+" . ($i+1) . " weeks -1 day")),
				);
		}

		return $this->week_details;
	}


	/**
	 * Get the flag that checks if fully scheduled (100%) projects need to be
	 * displayed or not.
	 *
	 * The value is taken from the request or the first parameter and then is
	 * cached internally.
	 *
	 * @param boolean|null $hide_fully_scheduled_projects
	 *                            The hide flag from the request,
	 *                            if not passed in will be taken from
	 *                            $_GET['hide_fully_scheduled_projects']
	 * @return boolean Should complete projects be hidden or not
	 */
	private function getHideFullyScheduledProjects($hide_fully_scheduled_projects = null)
	{
		//return if already cached internally
		if (isset($this->hide_fully_scheduled_projects)) {
			return $this->hide_fully_scheduled_projects;
		}

		//get what was passed in or on the request
		//and then cache internally
		$this->hide_fully_scheduled_projects = isset($hide_fully_scheduled_projects) ?
												$hide_fully_scheduled_projects :
												isset($_GET['hide_fully_scheduled_projects']);

		return $this->hide_fully_scheduled_projects;
	}

	/**
	 * Check if the user requested to force refresh the data (bypass cache).
	 *
	 * @return boolean Refresh the data or not
	 */
	private function shouldDataBeRefreshed($post)
	{
		return isset($post['force_refresh']);
	}

	/**
	 * Get the URL query string for the start date and week count passed in.
	 *
	 * The query string will contain an extra parameter of
	 * hide_fully_scheduled_projects if the completed projects are to be hidden
	 * 
	 * @param string|null $week_start The starting date to be used in the query
	 *                                string. If null passed in will not be
	 *                                included at all.
	 * @param string|null $week_count The week count to be used in the query
	 *                                string. If null passed in will not be
	 *                                included at all.
	 * @param array $extras An array of additional values to be added to the
	 *                      query string. If you pass in a week count or
	 *                      starting date values here will take precendence
	 *                      and will be at the end of the parameters list.
	 * @return string The URL qs for the params provided
	 */
	private function getURLQueryString($week_start, $week_count, $extras = array())
	{
		$qs = array(
			'week_start' => $week_start,
			'week_count' => $week_count,
			);
		if ($this->getHideFullyScheduledProjects()) {
			$qs['hide_fully_scheduled_projects'] = '';
		}

		foreach ($extras as $param => $value) {
			if (array_key_exists($param, $qs)) {
				unset($qs[$param]);
			}
			$qs[$param] = $value;
		}

		return '?' . http_build_query($qs);
	}

	/**
	 * Get the URL query string that will reset the view to the week before the
	 * current one.
	 *
	 * The URL contains the current week count, and -1 week from today as the
	 * starting date. Within getScheduleStartingDay() this will be corrected to
	 * the Monday in that week.
	 * 
	 * @return string The URL qs for switching to the start of the
	 *                previous week
	 */
	private function getURLQueryStringWeekBefore()
	{
		$week_start = date('Y-m-d', strtotime($this->getScheduleStartingDay() . " -1 week"));
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string that will reset the view to the week after the
	 * current one.
	 *
	 * The URL contains the current week count, and +1 week from today as the
	 * starting date. Within getScheduleStartingDay() this will be corrected to
	 * the Monday in that week.
	 * 
	 * @return string The URL qs for switching to the start of the next week
	 */
	private function getURLQueryStringWeekAfter()
	{
		$week_start = date('Y-m-d', strtotime($this->getScheduleStartingDay() . " +1 week"));
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string that will reset the view to the start of the
	 * current week.
	 *
	 * The URL contains the current week count, and today as the starting date.
	 * Within getScheduleStartingDay() this will be corrected to the Monday for
	 * the current week.
	 * 
	 * @return string The URL qs for switching to the start of the current week
	 */
	private function getURLQueryStringThisWeek()
	{
		$week_start = date('Y-m-d');
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string that will reset the view to the start of the
	 * current month.
	 *
	 * The URL contains the current week count, but no starting date - the rest
	 * of the application assumes that this means we want to see the data from
	 * the start of the current month.
	 * 
	 * @return string The URL qs for switching to the start of the
	 *                current month
	 */
	private function getURLQueryStringThisMonth()
	{
		$week_start = null;
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string with one week more to display.
	 *
	 * The URL contains the current starting date and a week count that's one
	 * more than the current one.
	 * 
	 * @return string The URL qs with one week more
	 */
	private function getURLQueryStringWeekMore()
	{
		$week_start = $this->getScheduleStartingDay();
		$week_count = $this->getWeeksToShowCount() + 1;
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string with one week less to display.
	 *
	 * The URL contains the current starting date and a week count that's one
	 * less from the current one.
	 * 
	 * @return string The URL qs with one week less
	 */
	private function getURLQueryStringWeekLess()
	{
		$week_start = $this->getScheduleStartingDay();
		$week_count = $this->getWeeksToShowCount() - 1;
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string that will be used to change the starting date.
	 *
	 * The URL contains the current duration with an empty starting date at
	 * the end of the url.
	 * 
	 * @return string The URL qs used when changing the start date
	 */
	private function getURLQueryStringWeekStartChange()
	{
		$week_start = '';
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count, array('week_start' => ''));
	}

	/**
	 * Get the URL query string that will be used to change the week count.
	 *
	 * The URL contains the current starting date with an empty duration.
	 * 
	 * @return string The URL qs used when changing the number of
	 *                weeks to display
	 */
	private function getURLQueryStringWeekCountChange()
	{
		$week_start = $this->getScheduleStartingDay();
		$week_count = '';
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL query string that will be used for force refreshing the view
	 * (reloading the data without any caching).
	 *
	 * The URL contains the current starting date and duration.
	 * 
	 * @return string The URL qs used when force refreshing the view
	 */
	private function getURLQueryStringForceRefreshAction()
	{
		$week_start = $this->getScheduleStartingDay();
		$week_count = $this->getWeeksToShowCount();
		return $this->getURLQueryString($week_start, $week_count);
	}

	/**
	 * Get the URL for downloading the PDF from teamweek.
	 *
	 * The URL is built up based on the schedule starting date and the schedule
	 * duration (in days).
	 * 
	 * @return string The URL used to download the PDF
	 */
	private function getURLForPDFDownload($teamweek_account_base_url)
	{
		$qs = array(
			'range_start' => $this->getScheduleStartingDay(),
			'days' => $this->getWeeksToShowCount() * 7,
			'group_id' => '',
			'project_ids' => '',
			'no_project' => 0,
			);

		return $teamweek_account_base_url . 'planner/pdf?' . http_build_query($qs);
	}

	/**
	 * Get the URL for submitting the requirements.
	 * 
	 * @return string The URL used for posting the requirement with ajax
	 */
	private function getURLForAjaxRequirementPost()
	{
		return $_SERVER['PHP_SELF'];
	}

	/**
	 * Handle any exception thrown
	 * 
	 * Displays the error page with a stacktrace from the error.
	 * In case an exception is thrown during this handler this is caught and
	 * basic details of that new exception are shown.
	 *
	 */
	public function handleException($exception)
	{
		try	{

			$view_data = array(
					'error_message' => $exception->getMessage(),
					'error_details' => nl2br($exception->getTraceAsString())
				);

			$this->showView('error.tpl', $view_data);

		} catch (Exception $new_exception) {
			//basic fallback for an exception within an exception
			echo "<h1>Internal Error</h1>";
			echo "<h3>" . $new_exception->getMessage() . "</h3>";
			echo "<p>Exception thrown in " . $new_exception->getFile() . " on line " . $new_exception->getLine() . "</p>";
		}
	}

	/**
	 * Show the specified view file with the data provided.
	 * 
	 * The $file is simply required right after the $view_data is extracted.
	 * This allows using the data provided in the array directly as variables
	 * inside the template file.
	 *
	 * @param string $file path/file to where the template file is
	 * @param array $view_data array with data to make available in the view
	 * @throws Exception If the view file does not exist
	 */
	private function showView($file, $view_data)
	{
		if (!file_exists($file)) {
			throw new Exception("Could not find view '$file'");
		}

		extract($view_data);
		require_once $file;
	}
}