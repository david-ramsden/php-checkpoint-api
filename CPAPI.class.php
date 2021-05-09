<?php
/**
 * Copyright (c) 2021 David Ramsden
 *
 * This software is provided 'as-is', without any express or implied
 * warranty. In no event will the authors be held liable for any damages
 * arising from the use of this software.
 *
 * Permission is granted to anyone to use this software for any purpose,
 * including commercial applications, and to alter it and redistribute it
 * freely, subject to the following restrictions:
 *
 * 1. The origin of this software must not be misrepresented; you must not
 *   claim that you wrote the original software. If you use this software
 *   in a product, an acknowledgment in the product documentation would be
 *   appreciated but is not required.
 * 2. Altered source versions must be plainly marked as such, and must not be
 *   misrepresented as being the original software.
 * 3. This notice may not be removed or altered from any source distribution.
 */

/**
 * Check Point API class.
 *
 * Used to interact with the Check Point Management API.
 * Compatible with Management API Version 1.3 and above.
 */
class CPAPI
{
	private $__ch;			// cURL handle.
	private $__sid = NULL;		// Check Point API session ID.
	private $__cli = FALSE;		// If being called from CLI or not.

	private $server = '';
	private $user = '';
	private $password = '';
	private $timeout = 10;
	private $readonly = FALSE;

	/**
	 * Class constructor.
	 *
	 * @param array $params		Parameters to set the object up with in an array.
	 */
	public function __construct($params = array())
	{
		// Set parameters for the instanciated object.
		foreach ($params as $key => $value) {
			if (isset($this->$key)) {
				$this->$key = $value;
			}
		}

		// Server, User and Password parameters are required.
		if (empty($this->server) || empty($this->user) || empty($this->password)) {
			throw new Exception('CPAPI::__construct(): No server, user or password provided');
		}

		// Create a cURL handle.
		$this->__ch = curl_init();

		// If $_SERVER['REQUEST_METHOD'] is not set, it means this is being run from the CLI.
		if (!isset($_SERVER['REQUEST_METHOD'])) {
			$this->__cli = TRUE;
		}
	}

	/**
	 * Class destructor.
	 */
	public function __destruct()
	{
		try {
			// If there is a session ID, clean it up by discarding the Check Point session,
			// and logging out.
			if (isset($this->__sid)) {
				$this->send_request('discard');
				$this->logout();
			}
		} finally {
			// Close the cURL handle.
			@curl_close($this->__ch);
		}
	}

	/**
	 * Send a request to the Check Point Management API.
	 *
	 * @param string $uri	The URI part of the API.
	 * @param array $data	Any data, as an array, to send with the request.
	 */
	public function send_request($uri, $data = "")
	{
		// If a request is being sent but there's no session ID (returned on login) and the request is not to login,
		// send a login request first. This will happen if the session ID has timed out.
		if (!isset($this->__sid) && $uri !== 'login') {
			$this->login();
		}

		curl_setopt($this->__ch, CURLOPT_URL, "https://$this->server/web_api/$uri");
		curl_setopt($this->__ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->__ch, CURLOPT_HEADER, FALSE);
		curl_setopt($this->__ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->__ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($this->__ch, CURLOPT_CUSTOMREQUEST, 'POST');

		if (!empty($data)) {
			// If some data needs to be sent with the request, encode it as JSON.
			curl_setopt($this->__ch, CURLOPT_POSTFIELDS, json_encode($data));
		} else {
			curl_setopt($this->__ch, CURLOPT_POSTFIELDS, '{ }');
		}

		$headers = array('Content-Type: application/json');
		if (isset($this->__sid)) {
			// There is a session ID, so send that with the request.
			array_push($headers, "X-chkp-sid: $this->__sid");
		}
		curl_setopt($this->__ch, CURLOPT_HTTPHEADER, $headers);

		// Get response from request.
		if (($out = curl_exec($this->__ch)) === FALSE) {
			// Request failed. Likely a protocol issue.
			// Throw an exception.
			throw new Exception('CPAPI::send_request(): Error executing request: ' . curl_error($this->__ch));
		}

		// Request was successful from a protocol point of view.
		// Fetch the HTTP status code returned.
		$response_code = curl_getinfo($this->__ch, CURLINFO_RESPONSE_CODE);
		// If the HTTP status code was not 200 (OK), there was an API error.
		if ($response_code !== 200) {
			// The error will be JSON encoded. There will be a 'code' (which is a text code) and 'message' element.
			$json = json_decode($out);
			switch ($json->{'code'}) {
				case 'generic_err_session_expired':
				case 'generic_err_wrong_session_id':
					// If the API is saying the session has expired or the wrong session ID was sent in the request,
					// void the session ID, send another login request and try the request again.
					$this->__sid = NULL;
					$this->login();
					$this->send_request($uri, $data);
					break;
				default:
					// Some other error happened. Throw an exception with the code and message.
					throw new Exception('CPAPI::send_request(): Check Point returned API error: ' . $json->{'code'} . ' (' . $json->{'message'} . ')');
					break;
			}
		}

		return json_decode($out);
	}

	private function login()
	{
		if (!empty($_SESSION['user'])) {
			$description = "Published by web user: " . $_SESSION['user'];
		} elseif ($this->__cli === TRUE) {
			$description = "Published by CLI user: " . exec('whoami');
		} else {
			$description = "Published by CPAPI (user unknown)";
		}

		$data = array(
			'user'			=> $this->user,
			'password'		=> $this->password,
			'read-only'		=> $this->readonly,
			'session-timeout'	=> $this->timeout,
			'session-description'	=> $description,
		);

		try {
			$this->__sid = $this->send_request('login', $data)->{'sid'};
		} catch (Exception $e) {
			throw new Exception("CPAPI::login(): Error logging in because " . $e->getMessage());
		}
	}

	public function logout()
	{
		if (isset($this->__sid)) {
			$this->send_request('logout');
			$this->__sid = NULL;
		}
	}

	public function publish()
	{
		set_time_limit(0);
		if (!$this->__cli) {
			ob_implicit_flush(true);
			ob_end_flush();
		}

		if (!$this->changes()) {
			echo "No changes to publish\n";
			return;
		}

		$result = $this->send_request('publish');
		$taskid = $result->{'task-id'};

		if ($this->__cli) {
			echo "Publishing: ";
		}

		$percent_last = -1;
		$max_time = 300;
		for ($i = 0; $i < ($max_time / 5); $i++) {
			list($status, $percent, $details) = $this->task_status($taskid);

			if ($percent_last !== $percent) {
				if ($this->__cli) {
					echo "$percent% ";
					$percent_last = $percent;
				}
			}

			if ($status !== 'in progress' || $percent === 100) {
				break;
			}

			if ($i >= ($max_time / 5)) {
				$status = 'timed out waiting for task';
				$details = array();
			}

			sleep(5);
		}

		if ($this->__cli) {
			echo "\n";
		}
	}

	private function changes()
	{
		return intval($this->send_request('show-session')->{'changes'});
	}

	public function task_status($id)
	{
		if (empty($id)) {
			return array();
		}

		$tasks = $this->send_request('show-task', array('task-id' => $id));

		foreach ($tasks->{'tasks'} as $task) {
			if ($task->{'task-id'} === $id) {
				return array($task->{'status'}, $task->{'progress-percentage'}, empty($task->{'task-details'}) ? array() : $task->{'task-details'});
			}
		}

		return array();
	}
}
?>
