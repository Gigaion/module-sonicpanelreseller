<?php
use Blesta\Core\Util\Validate\Server;
/**
 * Sonicpanel Module.
 *
 * @copyright Gigaion LLC
 * @license MIT License
 * @see license.md (MIT License)
 */
class Sonicpanelreseller extends Module
{
    /**
     * Initializes the module.
     */
    public function __construct()
    {
        // Load configuration required by this module
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input', 'Net']);
        $this->Http = $this->Net->create('Http');

        // Load the language required by this module
        Language::loadLang('sonicpanelreseller', null, dirname(__FILE__) . DS . 'language' . DS);
		
		
		$this->debug = false;
		$this->logfile('RUNNING Sonicpanel construct: started module...');
    }
	
	
	public function logfile($data='') {
		if($this->debug) {
			$logfile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'logfile.log';
			file_put_contents($logfile, $data."\n", FILE_APPEND | LOCK_EX);
		}
	}
	
    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: ['methodName' => 'Title', 'methodName2' => 'Title2']
     */
    public function getAdminTabs($package)
    {
        return [
            'tabStats' => Language::_('Sonicpanelreseller.tab_stats', true)
        ];
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: ['methodName' => 'Title', 'methodName2' => 'Title2']
     */
    public function getClientTabs($package)
    {
        return [
            'tabClientActions' => Language::_('Sonicpanelreseller.tab_client_actions', true),
            'tabClientStats' => Language::_('Sonicpanelreseller.tab_stats', true)
        ];
    }

    /**
     * Returns an array of available service deligation order methods. The module
     * will determine how each method is defined. For example, the method "first"
     * may be implemented such that it returns the module row with the least number
     * of services assigned to it.
     *
     * @return array An array of order methods in key/value paris where the key is the type
     *  to be stored for the group and value is the name for that option
     * @see Module::selectModuleRow()
     */
    public function getGroupOrderOptions()
    {
        return ['first' => Language::_('Sonicpanelreseller.order_options.first', true)];
    }

    /**
     * Determines which module row should be attempted when a service is provisioned
     * for the given group based upon the order method set for that group.
     *
     * @return int The module row ID to attempt to add the service with
     * @see Module::getGroupOrderOptions()
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        $group = $this->ModuleManager->getGroup($module_group_id);

        if ($group) {
            switch ($group->add_order) {
                default:
                case 'first':
                    foreach ($group->rows as $row) {
                        return $row->id;
                    }

                    break;
            }
        }
        return 0;
    }

    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containing the fields to render as
     *  well as any additional HTML markup to include
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();
		
        // Fetch all packages available for the given server or server group
        $module_row = null;
        if (isset($vars->module_group) && $vars->module_group == '') {
            if (isset($vars->module_row) && $vars->module_row > 0) {
                $module_row = $this->getModuleRow($vars->module_row);
            } else {
                $rows = $this->getModuleRows();
                if (isset($rows[0])) {
                    $module_row = $rows[0];
                }
                unset($rows);
            }
        } else {
            // Fetch the 1st server from the list of servers in the selected group
            $rows = $this->getModuleRows($vars->module_group);
            if (isset($rows[0])) {
                $module_row = $rows[0];
            }
            unset($rows);
        }

        $package = $fields->label(Language::_('Sonicpanelreseller.package_fields.package', true), 'package');
		
        $package->attach(
            $fields->fieldSelect(
                'meta[package]',
                $this->getPackagesList($vars),
                $this->Html->ifSet($vars->meta['package'])
            ),
            ['id' => 'package']
        );
        $fields->setField($package);

        return $fields;
    }

    /**
     * Retrieves a list of packages from SonicPanel username account
     *
     * @return array of SonicPanel packages
     */
	
    private function getPackagesList($vars=array())
    {
		$result = array();
		if(!empty($vars->{'module_row'})) {
			$row = $this->getModuleRow($vars->{'module_row'});
			$api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);
			$packages = $api->getPackagesList();
			if(!empty($packages['response'])) {
				$packages = explode(',', $packages['response']);
				if(is_array($packages)) {
					foreach($packages as $package) {
						if(!empty($package)) {
							$result[$package] = $package;
						}
					}
				}
			}
		}
		$result[Language::_('Sonicpanelreseller.service_field.module_configoptionmessage', true)] = Language::_('Sonicpanelreseller.service_field.module_configoptionmessage', true);
		
		if(empty($result)) {
			return [
				Language::_('Sonicpanelreseller.service_field.module_package_error', true) => Language::_('Sonicpanelreseller.service_field.module_package_error', true)
			];
		}
		else {
			return $result;
		}
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package. Performs any action required to add
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being added.
     *
     * @param array An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addPackage(array $vars = null)
    {
        // Set rules to validate input data
        $this->Input->setRules($this->getPackageRules($vars));

        // Build meta data to return
        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package. Performs any action required to edit
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being edited.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editPackage($package, array $vars = null)
    {
        // Set rules to validate input data
        $this->Input->setRules($this->getPackageRules($vars));

        // Build meta data to return
        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Returns the rendered view of the manage module page.
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manager module
     *  page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page.
     *
     * @param array $vars An array of post data submitted to or on the add module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        // Set unspecified checkboxes
        if (!empty($vars)) {
            if (empty($vars->usessl)) {
                $vars->usessl = 'false';
            }
			if (empty($vars->useproxy)) {
               $vars->useproxy = 'false';
            }
        }

        $this->view->set('vars', (object) $vars);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);
        $this->Input->setRules($this->getRowRules($vars));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
        } 
		
		// Set unspecified checkboxes
        if (!empty($vars)) {
            if (empty($vars->usessl)) {
                $vars->usessl = 'false';
            }
			if (empty($vars->useproxy)) {
               $vars->useproxy = 'false';
            }
        }

        $this->view->set('vars', (object) $vars);

        return $this->view->fetch();
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added. Returns a set of data, which may be
     * a subset of $vars, that is stored for this module row.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars)
    {
		
        $meta_fields = ['server_name', 'ipaddress', 'usessl', 'adminapikey', 'adminusername', 'hostname', 'useproxy'];
        $encrypted_fields = ['adminapikey'];

        // Set unspecified checkboxes
        if (!empty($vars)) {
            if (empty($vars['usessl'])) {
                $vars['usessl'] = 'false';
            }
			if (empty($vars['useproxy'])) {
               $vars['useproxy'] = 'false';
            }
        }
		
        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            $vars['ipaddress'] = strtolower($vars['ipaddress']);

            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }
            return $meta;
        }
    }

    /**
     * Edits the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being updated. Returns a set of data, which may be
     * a subset of $vars, that is stored for this module row.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function editModuleRow($module_row, array &$vars)
    {
        $meta_fields = ['server_name', 'ipaddress', 'usessl', 'adminapikey', 'adminusername', 'hostname', 'useproxy'];
        $encrypted_fields = ['apikey'];

        // Set unspecified checkboxes
        if (!empty($vars)) {
            if (empty($vars['usessl'])) {
                $vars['usessl'] = 'false';
            }
			if (empty($vars['useproxy'])) {
               $vars['useproxy'] = 'false';
            }
        }

        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            $vars['ipaddress'] = strtolower($vars['ipaddress']);

            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Returns all fields to display to an admin attempting to add a service with the module.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();
		
		
		$radiousername = $fields->label(Language::_('Sonicpanelreseller.service_field.radiousername', true), 'radiousername');
		$radiousername->attach(
			$fields->fieldText(
				'radiousername',
				$this->Html->ifSet($vars->radiousername),
				['id' => 'radiousername']
			)
		);
		$fields->setField($radiousername);

		$radiopassword = $fields->label(Language::_('Sonicpanelreseller.service_field.radiopassword', true), 'radiopassword');
		$radiopassword->attach($fields->fieldPassword('radiopassword', ['id' => 'radiopassword']));
		$fields->setField($radiopassword);
		
        return $fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well as
     *  any additional HTML markup to include
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);
        $fields = new ModuleFields();
		
        return $fields;
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service with the module.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well
     *  as any additional HTML markup to include
     */
    public function getAdminEditFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        $fields = new ModuleFields();

        $radiousername = $fields->label(Language::_('Sonicpanelreseller.service_field.radiousername', true), 'radiousername');
        $radiousername->attach(
            $fields->fieldText(
                'radiousername',
                $this->Html->ifSet($vars->radiousername),
                ['id' => 'radiousername']
            )
        );
        $radiousername->attach($fields->tooltip(Language::_('Sonicpanelreseller.service_field.radiousername.tooltip', true)));
        $fields->setField($radiousername);

        $radiopassword = $fields->label(
            Language::_('Sonicpanelreseller.service_field.radiopassword', true),
            'radiopassword'
        );
        $radiopassword->attach($fields->fieldPassword('radiopassword', ['id' => 'radiopassword']));
        $radiopassword->attach($fields->tooltip(Language::_('Sonicpanelreseller.service_field.radiopassword.tooltip', true)));
        $fields->setField($radiopassword);
		
        return $fields;
    }

    /**
     * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return bool True if the service validates, false otherwise. Sets Input errors when false.
     */
    public function validateService($package, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars));
        return $this->Input->validates($vars);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates. Sets Input errors on failure.
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request
     * @return bool True if the service update validates or false otherwise. Sets Input errors when false.
     */
    public function validateServiceEdit($service, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars, true));
        return $this->Input->validates($vars);
    }

    /**
     * Returns the rule set for adding/editing a service
     *
     * @param array $vars A list of input vars
     * @param bool $edit True to get the edit rules, false for the add rules
     * @return array Service rules
     */
    private function getServiceRules(array $vars = null, $edit = false)
    {
        $rules = [
            'radiousername' => [
                'empty' => [
                    'if_set' => true,
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sonicpanelreseller.!error.radiousername.empty', true)
                ]
            ],
        ];

        // Set the values that may be empty
        if ($edit) {
            if (!array_key_exists('radiousername', $vars) || $vars['radiousername'] == '') {
                unset($rules['radiousername']);
            }
        }

        return $rules;
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being added (if the current service is an addon service service and parent
     *  service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *    - active
     *    - canceled
     *    - pending
     *    - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        $row = $this->getModuleRow();
        $params = [];

        if (!$row) {
            $this->Input->setErrors(
                ['module_row' => ['missing' => Language::_('Sonicpanelreseller.!error.module_row.missing', true)]]
            );

            return;
        }

        Loader::loadModels($this, ['Clients']);

        if (isset($vars['client_id']) && ($client = $this->Clients->get($vars['client_id'], false))) {
            $params['radioemail'] = $client->email;
            $params['radiocustomername'] = $client->first_name . ' ' . $client->last_name;
        }
		
		//Check isset first, to prevent uninitialized variable. Then set to empty string.
		if(!isset($params['radiousername'])) {
			$params['radiousername'] = '';
		}
		if(!isset($params['radiopassword'])) {
			$params['radiopassword'] = '';
		}
		
		//If variable is empty string, set to pending.
		if($params['radiousername'] == '') {
			$params['radiousername'] = 'pending';
		}
		if($params['radiopassword'] == '') {
			$params['radiopassword'] = 'pending';
		}
		
		//Admin on Blesta specified a username/password with module usage set to false. (Used for manually added an existing created radio account to a client)
		if ($vars['use_module'] != 'true') {
			if(isset($vars['radiousername'])) {
				$params['radiousername'] = $vars['radiousername'];
			}
			if(isset($vars['radiousername'])) {
				$params['radiopassword'] = $vars['radiopassword'];
			}
		}
		
		//Use configurable options by default (Allows for upgrading between plans)
		$params['package'] = $vars['configoptions']['package'];
		//Fallback to package only plans. Not as much flexibility for switching plans.
		if(empty($params['package'])) {
			//Fallback to package only plans. Not as much flexibility for switching plans.
			$params['package'] = $package->meta->package;
		}
		
		$this->logfile('RUNNING addService: validate service...');
		
        $this->validateService($package, $params);
		$this->logfile('RUNNING addService: validate service... IT RAN');
		
        if ($this->Input->errors()) {
            return;
        }
		
        $api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);
		$this->logfile('RUNNING addService: started api');
		
        // Only provision the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
			$this->logfile('RUNNING addService: IS MODULE entered');
			
			$params['radiousername'] = $this->generateUsername($client->first_name . $client->last_name);
			$params['radiopassword'] = $this->generatePassword(10, 14);
			
            $masked_params = $params;
            $masked_params['radiopassword'] = '***';

			//Base64 encode. As names can contain non utf-8 data which Blesta database does not like. (It will cause PDO error if non-UTF8)
            $this->log($row->meta->ipaddress . '|create', base64_encode(serialize($masked_params)), 'input', true);
            $response = $this->parseResponse($api->createRadio($params));
			
			$this->logfile('RUNNING addService: parsed response');
            if ($this->Input->errors()) {
                return;
            }
			$this->logfile('RUNNING addService: no errors found from above');
        }

        // Return service fields
        return [
            [
                'key' => 'radiousername',
                'value' => $params['radiousername'],
                'encrypted' => 0
            ],
            [
                'key' => 'radiopassword',
                'value' => $params['radiopassword'],
                'encrypted' => 1
            ]
        ];
    }

    /**
     * Edits the service on the remote server. Sets Input errors on failure,
     * preventing the service from being edited.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent service's selected
     *  package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of the
     *  service being edited (if the current service is an addon service)
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editService($package, $service, array $vars = null, $parent_package = null, $parent_service = null)
    {
		
        $this->validateServiceEdit($service, $vars);

        if ($this->Input->errors()) {
            return;
        }

        $service_fields = $this->serviceFieldsToObject($service->fields);

		//Use configurable options by default (Allows for upgrading between plans)
		$params['package'] = $vars['configoptions']['package'];
		
		//Fallback to package only plans. Not as much flexibility for switching plans.
		if(empty($params['package'])) {
			//Fallback to package only plans. Not as much flexibility for switching plans.
			$params['package'] = $package->meta->package;
		}

        if (empty($vars['radiousername'])) {
            $vars['radiousername'] = $service_fields->radiousername;
        }

        if (empty($vars['radiopassword'])) {
            $vars['radiopassword'] = $service_fields->radiopassword;
        }
		
		
		if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);
			
            $api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);
			
			if(!empty($vars['radiopassword'])) {
				//Update Password
				$this->log(
					$row->meta->ipaddress . '|changepassword',
					serialize(array($service_fields->radiousername, $vars['radiopassword'])),
					'input',
					true
				);
				$response = $this->parseResponse($api->changePassword($service_fields->radiousername, $vars['radiopassword']));
				
				if($this->Input->errors()) {
					return;
				}
			}
			
			//Not yet coded by Sonicpanel devlopers for switching package
			/*
			if(!empty($vars['configoptions']['package'])) {
				//Update package
				$response = $this->parseResponse($api->changePackage($service_fields->radiousername, $vars['configoptions']['package']));
				
				if($this->Input->errors()) {
					return;
				}
			}
			*/
			
		}
		

        // Return service fields
        return [
            [
                'key' => 'radiousername',
                'value' => $vars['radiousername'],
                'encrypted' => 0
            ],
            [
                'key' => 'radiopassword',
                'value' => $vars['radiopassword'],
                'encrypted' => 1
            ]
        ];
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being suspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being suspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array of
     *  meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);
			
            $api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);

            $this->log(
                $row->meta->ipaddress . '|suspend',
                serialize($service_fields->radiousername),
                'input',
                true
            );
            $response = $api->suspendRadio($service_fields->radiousername);
            $this->log($row->meta->ipaddress . '|suspend', serialize($response), 'output', $response['status']);

            // If the action fails then set an error
            if (!$response['status']) {
                $this->Input->setErrors(['api' => ['error' => '[Suspend] '.Language::_('Sonicpanelreseller.!error.api.internal', true)]]);
            }
        }

        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being unsuspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being unsuspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array
     *  of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);
			
            $api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);

            $this->log(
                $row->meta->ipaddress . '|unsuspend',
                serialize($service_fields->radiousername),
                'output',
                true
            );
            $response = $api->unSuspendRadio($service_fields->radiousername);
            $this->log($row->meta->ipaddress . '|unsuspend', serialize($response), 'output', $response['status']);

            // If the action fails then set an error
            if (!$response['status']) {
                $this->Input->setErrors(['api' => ['error' => '[Unsuspend] '.Language::_('Sonicpanelreseller.!error.api.internal', true)]]);
            }
        }

        return null;
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's
     *  selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of
     *  the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed
     *  array of meta fields to be stored for this service containing:
     *    - key The key for this meta field
     *    - value The value for this key
     *    - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $service_fields = $this->serviceFieldsToObject($service->fields);
			
            $api = $this->getApi($row->meta->adminapikey, $row->meta->ipaddress, $row->meta->usessl, $row->meta->adminusername, $row->meta->useproxy);

            $this->log(
                $row->meta->ipaddress . '|terminate',
                serialize([$service_fields->radiousername]),
                'input',
                true
            );
            $response = $api->terminateRadio($service_fields->radiousername);
            $this->log($row->meta->ipaddress . '|terminate', serialize($response), 'output', $response['status']);

            // If the action fails then set an error
            if (!$response['status']) {
                $this->Input->setErrors(['api' => ['error' => '[Cancel] '.Language::_('Sonicpanelreseller.!error.api.internal', true)]]);
            }
        }

        return null;
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * admin interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getAdminServiceInfo($service, $package)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
		
		$row = $this->getModuleRow();
		$this->view->set('service_id', $service->id);
		$this->view->set('package_fields', $package->meta);
		$this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * client interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getClientServiceInfo($service, $package)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
		
		$row = $this->getModuleRow();
		$this->view->set('service_id', $service->id);
		$this->view->set('package_fields', $package->meta);
		$this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    /**
     * Client Actions (reset password)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_actions', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
		
        // Perform the password reset
        if (!empty($post)) {
            Loader::loadModels($this, ['Services']);
            $data = [
                'radiopassword' => $this->Html->ifSet($post['radiopassword'])
            ];
            $this->Services->edit($service->id, $data);

            if ($this->Services->errors()) {
                $this->Input->setErrors($this->Services->errors());
            }

            $vars = (object)$post;
        }
		$this->view->set('vars', (isset($vars) ? $vars : new stdClass()));
		
		
		$row = $this->getModuleRow();
		$this->view->set('service_id', $service->id);
		$this->view->set('package_fields', $package->meta);
		$this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));
		
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);
        return $this->view->fetch();
    }

    /**
     * Client Statistics tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_stats', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
		
		$row = $this->getModuleRow();
		$this->view->set('service_id', $service->id);
		$this->view->set('package_fields', $package->meta);
		$this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));
		
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);
		
        return $this->view->fetch();
    }

    /**
     * Statistics tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_stats', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);
		
		$row = $this->getModuleRow();
		$this->view->set('service_id', $service->id);
		$this->view->set('package_fields', $package->meta);
		$this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'sonicpanelreseller' . DS);
        return $this->view->fetch();
    }

    /**
     * Validates that the given hostname is valid.
     *
     * @param string $host_name The host name to validate
     * @return bool True if the hostname is valid, false otherwise
     */
    public function validateHostName($host_name)
    {
        $validator = new Server();
        return $validator->isDomain($host_name) || $validator->isIp($host_name);
    }

    /**
     * Validates that the given hostname is valid.
     *
     * @param string $host_name The host name to validate
     * @return bool True if the hostname is valid, false otherwise
     */
    public function validateIP($ip)
    {
        $validator = new Server();
        return $validator->isIp($ip);
    }

    /**
     * Generates a password.
     *
     * @param int $min_length The minimum character length for the password (5 or larger)
     * @param int $max_length The maximum character length for the password (14 or fewer)
     * @return string The generated password
     */
    private function generatePassword($min_length = 10, $max_length = 14)
    {
        $pool = 'abcdefghjkmnpqrstuvwxyz0123456789'; //excluded i,l,o to prevent confusion
        $pool_size = strlen($pool);
        $length = mt_rand(max($min_length, 5), min($max_length, 14));
        $password = 'SP';
		
        for ($i = 0; $i < $length; $i++) {
            $password .= substr($pool, mt_rand(0, $pool_size - 1), 1);
        }

        return $password;
    }

    /**
     * Generates mostly random username partially based on the client's name.
     *
     * @param mixed $name The client's name
     * @return string The username generated from the given client name
     */
    private function generateUsername($name)
    {
        // Use the first two characters if the name
        //$username = substr(str_replace(' ', '', strtolower($name)), 0, 2);
		
		//No longer use based on name. Due to non UTF-8 names can cause ? characters non compatiable with linux
		$lengthRandomUsername = 5;
		$username = substr(str_shuffle("abcdefghjkmnpqrstuvwxyz"), 0, $lengthRandomUsername); //excluded i,l,o to prevent confusion
		
        $length = strlen($username);

        $pool = 'abcdefghjkmnpqrstuvwxyz0123456789'; //excluded i,l,o to prevent confusion
        $pool_size = strlen($pool);

        for ($i = $length; $i < 8; $i++) {
            $username .= substr($pool, mt_rand(0, $pool_size - 1), 1);
        }
		
		return 'spr_' . $username;
    }

    /**
     * Parses the response from the API into a stdClass object.
     *
     * @param stdClass $response The response from the API
     * @return stdClass A stdClass object representing the response, void if the response was an error
     */
    private function parseResponse($response)
    {
        $row = $this->getModuleRow();
        $success = true;

        if ($response['status'] == false) {
            $this->Input->setErrors(['api' => ['error' => '[Parse] '.Language::_('Sonicpanelreseller.!error.api.internal', true)]]);
            $success = false;
        }
		
        // Log the response
        $this->log($row->meta->ipaddress, '|parseResponse ' . serialize($response), 'output', $success);

        // Return if any errors encountered
        if (!$success) {
            return;
        }

        return $response;
    }

    /**
     * Initialize the API library.
     *
     * @param string $password The sonicpanel password
     * @param string $ipaddress The ip address of the server
     * @param bool $usessl Whether to use https or http
     * @return SonicpanelApi the SonicpanelApi instance, or false if the loader fails to load the file
     */
    private function getApi($adminapikey, $ipaddress, $usessl, $adminusername, $useproxy)
    {
        Loader::load(dirname(__FILE__) . DS . 'api' . DS . 'sonicpanelreseller_api.php');

        return new SonicpanelresellerApi($adminapikey, $ipaddress, $usessl, $adminusername, $useproxy);
    }

    /**
     * Builds and returns the rules required to add/edit a module row (e.g. server).
     *
     * @param array $vars An array of key/value data pairs
     * @return array An array of Input rules suitable for Input::setRules()
     */
    private function getRowRules(&$vars)
    {
		//Rules variables get entered into the function (eg: 'rule' => [[$this, 'example']]), in the order that the rules are specified below
        $rules = [
		    'server_name' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sonicpanelreseller.!error.server_name_valid', true)
                ]
            ],
			'ipaddress' => [
                'valid' => [
                    'rule' => [[$this, 'validateIP']],
                    'message' => Language::_('Sonicpanelreseller.!error.ipaddress_valid', true)
                ]
            ],
			'hostname' => [
                'valid' => [
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Sonicpanelreseller.!error.hostname_valid', true)
                ]
            ],
            'adminusername' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sonicpanelreseller.!error.adminusername_valid', true)
                ]
            ],
            'adminapikey' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sonicpanelreseller.!error.adminapikey_valid', true)
                ],
                'valid_connection' => [
                    'rule' => [
                        [$this, 'validateConnection'],
						isset($vars['server_name']) ? $vars['server_name'] : '',
						isset($vars['ipaddress']) ? $vars['ipaddress'] : '',
						isset($vars['hostname']) ? $vars['hostname'] : '',
						isset($vars['adminusername']) ? $vars['adminusername'] : '',
						isset($vars['adminapikey']) ? $vars['adminapikey'] : '',
						
						isset($vars['usessl']) ? $vars['usessl'] : 'false',
						isset($vars['useproxy']) ? $vars['useproxy'] : 'false'
                    ],
                    'message' => '[Validate Connection] '.Language::_('Sonicpanelreseller.!error.api.internal', true)
                ]
            ]
        ];
		
        return $rules;
    }

    /**
     * Builds and returns rules required to be validated when adding/editing a package.
     *
     * @return array An array of Input rules suitable for Input::setRules()
     */
    private function getPackageRules()
    {
        $rules = [
			'meta[package]' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Sonicpanelreseller.!error.meta[package].valid', true)
                ]
            ],
        ];

        return $rules;
    }

    /**
     * Validates whether or not the connection details are valid by attempting to terminate a non-existent radio
     *
     * @return bool True if the connection is valid, false otherwise
     */
    public function validateConnection($adminapikey, $servernamelabel, $ipaddress, $hostname, $adminusername, $usessl, $useproxy)
    {
		try {
            $api = $this->getApi($adminapikey, $ipaddress, $usessl, $adminusername, $useproxy);
            // Test connection by terminating a non-existent radio
            $result = $api->terminateRadio(['_#$%^3456#$%^#456[{g']);
            // Log the response
            $success = (isset($result['status']) && $result['status'] == true);
			return $success;
        }
		catch (Exception $e) {
            // Trap any errors encountered, could not validate connection
		}
        return false;
    }
}



?>