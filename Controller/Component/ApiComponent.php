<?php
/**
 * Quickly generate a REST-ful API for a CakePHP model
 *
 */
class ApiComponent extends Component {
	public $components = [
		'FormData.Crud' => [
			'respond' => 'json',
		], 
		'FormData.JsonResponse',
	];

	public $controller;

	public function __construct(ComponentCollection $collection, $settings = []) {
		$settings = array_merge(array(
			'prefix' => 'json',
		), $settings);
		return parent::__construct($collection, $settings);			
	}

/**
 * Initializes ApiComponent for use in the controller.
 *
 * @param Controller $controller A reference to the instantiating controller object
 * @return void
 */
	public function initialize(Controller $controller) {
		$this->controller = $controller;
		
		$this->JsonResponse->initialize($controller);
		$this->Crud->initialize($controller);

		$params = $controller->params;
		$method = $params['action'];
		$action = $method;
		if ($params['prefix']) {
			$action = substr($action, strlen($params['prefix']) + 1);
		}

		$headers = getallheaders();
		$message = "";

		$message = "HEADERS ===========\n";
		foreach ($headers as $k => $v) {
			$message .= "$k: $v\n";
		}

		$message .= "CONTENT ============\n";
		$message .= print_r($_POST, true);
		
		//mail('jamie@souperbowl.org', 'CHECK', $message);

		if (!method_exists($controller, $method) && ($response = $this->mapAction($action))) {
			$this->JsonResponse->output();
		}
		return parent::initialize($controller);
	}

/**
 * Commands
 *
 **/
	protected function mapAction($action) {
		$this->controller->render('FormData./Elements/blank');
		$this->controller->viewClass = 'Json';


		$params = $this->controller->params;
		$request = $this->controller->request;

		$pass = $params->pass;
		$isPost = $request->is('post');

		$this->Crud->jsonReponse = true;

		switch ($action):
			case 'get_form_defaults':
				$this->JsonResponse->set($this->Crud->getFormDefaults());
			break;
			case 'get_form_elements':
				$this->JsonResponse->set($this->Crud->getFormElements());
			break;
			case 'add':
				if ($isPost) {
					$this->Crud->create();
				} else {
					$this->JsonResponse->set([
						'formElements' => $this->Crud->getFormElements(),
						'data' => $this->Crud->getFormDefaults(),
					]);
				}
			break;
			case 'edit':
				if ($isPost) {
					$this->Crud->update($pass[0]);
				} else {
					$this->JsonResponse->set([
						'formElements' => $this->Crud->getFormElements($pass[0]),
						'data' => $this->Crud->read($pass[0]),
					]);
				}
			break;
			case 'view':
				$this->JsonResponse->set($this->Crud->read($pass[0]));
			break;
			case 'delete':
				$this->Crud->delete($pass[0]);
			break;
			case 'index':
				$varName = Inflector::pluralize($this->Crud->modelVariable);
				$result = $this->controller->paginate();
				$this->JsonResponse->set($varName, $result);
			break;
		endswitch;
		$json = $this->JsonResponse->get();
		return !empty($json) ? $json : false;
	}
}