<?php
App::uses('Router', 'Utility');

class JsonResponseComponent extends Component {
	public $name = 'JsonResponse';

/**
 * Outputs a generated JSON response and then exits, ensuring that is the only information displayed
 *
 * @param string|array $message The message to be displayed. Alternately you can send all variables via one array
 * @param bool $success Whether the referenced action was successful or not
 * @param string $redirect The url to redirect
 * @param int $id If present, the id of the referenced model
 * @param array $errors Any errors encountered while trying to complete the action
 * @return string A JSON-encoded list of data
 **/
	public function respond($message, $success = true, $redirect = null, $id = null, $errors = array()) {
		echo $this->response($message, $success, $redirect, $id, $errors);
		exit();
	}


/**
 * Generates a JSON-encoded response
 *
 * @param string|array $message The message to be displayed. Alternately you can send all variables via one array
 * @param bool $success Whether the referenced action was successful or not
 * @param string $redirect The url to redirect
 * @param int $id If present, the id of the referenced model
 * @param array $errors Any errors encountered while trying to complete the action
 * @return string A JSON-encoded list of data
 **/
	public function response($message, $success = true, $redirect = null, $id = null, $errors = array()) {
		if (is_array($message)) {
			$vars = $message;
		} else {
			$vars = array(
				'message' => $message,
				'success' => $success,
				'url' => $redirect,
				'id' => $id,
				'validation_errors' => $errors
			);
		}

		// Makes sure the URL is string formatted
		if (is_array($vars['url'])) {
			$vars['url'] = Router::url($vars['url'], true);
		}

		return json_encode($vars);
	}
}