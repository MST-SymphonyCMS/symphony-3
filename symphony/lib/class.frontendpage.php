<?php

	require_once(LIB . '/class.extensionmanager.php');
	require_once(LIB . '/class.xmldocument.php');	
	require_once(LIB . '/class.documentheaders.php');
	/*
	Class Parameter{
	
		private $value;
		private $key;
	
		public function __construct($key, $value){
			$this->value = $value;
			$this->key = $key;
		}
	
		public function __toString(){
			if(is_array($this->value)) return implode(',', $this->value);
			return (!is_null($this->value) ? $this->value : '');
		}
	}

	Final Class Register implements Iterator{

		private $parameters;

		private $_result;
		private $_position;
		private $_current;
		private $_keys;

		public function register(array $params){
			foreach($params as $key => $value) $this->$key = $value;
		}

		public function __construct(){
			$this->parameters = array();
			$this->_position = 0;
		}

		public function __set($name, $value){
			$this->parameters[$name] = new Parameter($name, $value);
			$this->_keys = array_keys($this->parameters);
		}

		public function __get($name){
			 if(isset($this->parameters[$name])) 
				return $this->parameters[$name];

			throw new Exception('No such parameter "' . $name . '"');
		}

		public function __isset($name){
			return (isset($this->parameters[$name]) && ($this->parameters[$name] instanceof Parameter));
		}

		public function current(){
			return current($this->parameters);
		}

		public function next(){
			$this->_position++;
			next($this->parameters);
		}

		public function position(){
			return $this->_position;
		}

		public function rewind(){
			reset($this->parameters);
			$this->_position = 0;
		}

		public function key(){
			return $this->_keys[$this->_position];
		}

		public function length(){
			return count($this->parameters);
		}

		public function valid(){
			return $this->_position < $this->length();
		}
	}
*/
	Class FrontendPage extends XMLDocument{
		
		public function __construct(){
			parent::__construct('1.0', 'utf-8');
			$this->Headers = new DocumentHeaders;
			
		}
		
		public function generate($page) {
			$full_generate = true;
			$devkit = null;
			$output = null;
			
			if (Frontend::instance()->isLoggedIn()) {
				####
				# Delegate: FrontendDevKitResolve
				# Description: Allows a devkit object to be specified, and stop continued execution:
				# Global: Yes
				Extension::notify(
					'FrontendDevKitResolve', '/frontend/',
					array(
						'full_generate'	=> &$full_generate,
						'devkit'		=> &$devkit
					)
				);
			}

			$this->_page = $page;
			$this->__buildPage();
			
			if ($full_generate) {
				####
				# Delegate: FrontendOutputPreGenerate
				# Description: Immediately before generating the page. Provided with the page object, XML and XSLT
				# Global: Yes
				Extension::notify(
					'FrontendOutputPreGenerate', '/frontend/',
					array(
						'page'	=> &$this,
						'xml'	=> $this->_xml,
						'xsl'	=> $this->_xsl
					)
				);
				
				$this->Headers->append('Content-Type', 'text/html; charset=utf-8');
				
				####
				# Delegate: FrontendPreRenderHeaders
				# Description: This is just prior to the page headers being rendered, and is suitable for changing them
				# Global: Yes
				Extension::notify('FrontendPreRenderHeaders', '/frontend/');
				
				// When the XSLT executes, it uses the CWD as set here
				$cwd = getcwd();
				chdir(WORKSPACE);
				$output = parent::generate();
				chdir($cwd);
				
				####
				# Delegate: FrontendOutputPostGenerate
				# Description: Immediately after generating the page. Provided with string containing page source
				# Global: Yes
				Extension::notify('FrontendOutputPostGenerate', '/frontend/', array('output' => &$output));

				
				if (is_null($devkit) && !$output) {
					throw new XSLProcException('Transformation Failed');
				}

			}
			
			if (!is_null($devkit)) {
				$devkit->prepare($this, $this->_pageData, $this->_xml, $this->_param, $output);
				return $devkit->build();
			}
			
			## EVENT DETAILS IN SOURCE
			if (Frontend::instance()->isLoggedIn() &&Symphony::Configuration()->core()->public->{'display-event-xml-in-source'} == 'yes') {
				$output .= PHP_EOL . '<!-- ' . PHP_EOL . $this->_events_xml->generate(true) . ' -->';
			}
			
			return $output;
		}
		
		private function __buildPage(){

			$start = precision_timer();
			
			try{
				$page = $this->resolvePage();
			}
			catch(Exception $e){

				$views = View::findFromType('404');
				$view = array_shift($views);

				if(!($view instanceof View)){
					throw new SymphonyErrorPage(
						__('The page you requested does not exist.'), 	
						__('Page Not Found'), NULL,
						array('HTTP/1.0 404 Not Found')
					);
				}

				$page = array(
					'id' => $view->guid,
					'path' => $view->parent()->path,
					'parent' => $view->parent()->handle,
					'title' => $view->title,
					'handle' => $view->handle,
					'params' => @implode('/', $view->{'url-parameters'}),
					'data_sources' => @implode(',', $view->{'data-sources'}),
					'events' => @implode(',', $view->events),
					'type' => $view->types,
					'filelocation' => $view->templatePathname()
				);

			}

			####
			# Delegate: FrontendPageResolved
			# Description: Just after having resolved the page, but prior to any commencement of output creation
			# Global: Yes
			Extension::notify('FrontendPageResolved', '/frontend/', array('env' => &$this->_env, 'page' => &$this, 'page_data' => &$page));

			$this->_pageData = $page;
			$root_page = @array_shift(explode('/', $page['path']));
			$current_path = explode(dirname($_SERVER['SCRIPT_NAME']), $_SERVER['REQUEST_URI'], 2);
			$current_path = '/' . ltrim(end($current_path), '/');

			// Get max upload size from php and symphony config then choose the smallest
			$upload_size_php = ini_size_to_bytes(ini_get('upload_max_filesize'));
			$upload_size_sym =Symphony::Configuration()->core()->symphony->{'maximum-upload-size'};

			$this->_param = array(
				'today' => DateTimeObj::get('Y-m-d'),
				'current-time' => DateTimeObj::get('H:i'),
				'this-year' => DateTimeObj::get('Y'),
				'this-month' => DateTimeObj::get('m'),
				'this-day' => DateTimeObj::get('d'),
				'timezone' => DateTimeObj::get('P'),
				'website-name' =>Symphony::Configuration()->core()->symphony->sitename,
				'symphony-version' =>Symphony::Configuration()->core()->symphony->version,
				'upload-limit' => min($upload_size_php, $upload_size_sym),
				'root' => URL,
				'workspace' => URL . '/workspace',
				'page-title' => $page['title'],
				'root-page' => ($root_page ? $root_page : $page['handle']),
				'current-page' => $page['handle'],
				'current-page-id' => $page['id'],
				'current-path' => $current_path,
				'parent-path' => '/' . $page['path'],
				'current-url' => URL . $current_path,
			);

			if(is_array($this->_env['url'])){
				foreach($this->_env['url'] as $key => $val) $this->_param[$key] = $val;
			}

			if(is_array($_GET) && !empty($_GET)){
				foreach($_GET as $key => $val){
					if(!in_array($key, array('symphony-page', 'debug', 'profile'))) $this->_param['url-' . $key] = $val;
				}
			}

			if(is_array($_COOKIE[__SYM_COOKIE_PREFIX__]) && !empty($_COOKIE[__SYM_COOKIE_PREFIX__])){
				foreach($_COOKIE[__SYM_COOKIE_PREFIX__] as $key => $val){
					$this->_param['cookie-' . $key] = $val;
				}
			}

			// Flatten parameters:
			General::flattenArray($this->_param);

			####
			# Delegate: FrontendParamsResolve
			# Description: Just after having resolved the page params, but prior to any commencement of output creation
			# Global: Yes
			Extension::notify('FrontendParamsResolve', '/frontend/', array('params' => &$this->_param));

			$xml_build_start = precision_timer();

			$xml = new XMLElement('data');
			$xml->setIncludeHeader(true);

			$events = new XMLElement('events');
			$this->processEvents($page['events'], $events);
			$xml->appendChild($events);

			$this->_events_xml = clone $events;

			$this->processDatasources($page['data_sources'], $xml);

			if(is_array($this->_env['pool']) && !empty($this->_env['pool'])){
				foreach($this->_env['pool'] as $handle => $p){

					if(!is_array($p)) $p = array($p);
					foreach($p as $key => $value){

						if(is_array($value) && !empty($value)){
							foreach($value as $kk => $vv){
								$this->_param[$handle] .= @implode(', ', $vv) . ',';
							}
						}

						else{
							$this->_param[$handle] = @implode(', ', $p);
						}
					}

					$this->_param[$handle] = trim($this->_param[$handle], ',');
				}
			}

			####
			# Delegate: FrontendParamsPostResolve
			# Description: Access to the resolved param pool, including additional parameters provided by Data Source outputs
			# Global: Yes
			Extension::notify('FrontendParamsPostResolve', '/frontend/', array('params' => $this->_param));

			$xParam = new XMLElement('parameters');
			foreach($this->_param as $key => $value){
				$xParam->appendChild(new XMLElement($key, General::sanitize($value)));
			}
			$xml->prependChild($xParam);

			$xsl = file_get_contents(VIEWS . '/' . $page['filelocation']); 
			
			/*$xsl = '<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:import href="' . VIEWS . '/' . $page['filelocation'] . '"/>
</xsl:stylesheet>';*/

			$this->setXML($xml->generate(true, 0));
			$this->setXSL($xsl, false);
			$this->setRuntimeParam($this->_param);
		
		}
/*
		public function resolvePage($page=NULL){
		
			if($page) $this->_page = $page;
			
			$view = NULL;
			
			####
			# Delegate: FrontendPrePageResolve
			# Description: Before page resolve. Allows manipulation of page without redirection
			# Global: Yes
			Extension::notify('FrontendPrePageResolve', '/frontend/', array('view' => &$view, 'page' => &$this->_page));

			if(is_null($view)){
				if(is_null($this->_page)){
					$views = View::findFromType('index');
					$view = array_shift($views);
				}
			
				else{
					$view = View::loadFromURL($this->_page);
				}
			}
			
			if(!($view instanceof View)) return;

			if(!Frontend::instance()->isLoggedIn() && in_array('admin', $view->types)){
				
				$views = View::findFromType('403');
				$view = array_shift($views);
				
				if(!($view instanceof View)){
					throw new SymphonyErrorPage( 
						__('Please <a href="%s">login</a> to view this page.', array(ADMIN_URL . '/login/')), 
						__('Forbidden'), NULL,
						array('HTTP/1.0 403 Forbidden')
					);
				}
			}
			
			$row = array(
				'id' => $view->guid,
				'path' => $view->parent()->path,
				'parent' => $view->parent()->handle,
				'title' => $view->title,
				'handle' => $view->handle,
				'params' => @implode('/', $view->{'url-parameters'}),
				'data_sources' => @implode(',', $view->{'data-sources'}),
				'events' => @implode(',', $view->events),
				'type' => $view->types,
				'filelocation' => $view->templatePathname()
			);
		
			if(isset($view->{'url-parameters'}) && is_array($view->{'url-parameters'})){
				foreach($view->{'url-parameters'} as $p){
					$this->_env['url'][$p] = NULL;
				}

				foreach($view->parameters() as $p => $v){
					$this->_env['url'][$p] = str_replace(' ', '+', $v);
				}
				
			}

			return $row;
	
		}
*/
		
		/*private function __fetchPageTypes($page_id){
			return Symphony::Database()->fetchCol('type', "SELECT `type` FROM `tbl_pages_types` WHERE `page_id` = '{$page_id}' ");
		}
		
		private function __isSchemaValid($page_id, $bits){
	
			$schema = Symphony::Database()->fetchVar('params', 0, "SELECT `params` FROM `tbl_pages` WHERE `id` = '".$page_id."' LIMIT 1");					
			$schema_arr = preg_split('/\//', $schema, -1, PREG_SPLIT_NO_EMPTY);		
	
			return (count($schema_arr) >= count($bits));
		
		}

		private static function resolvePageFileLocation($path, $handle){
			return (PAGES . '/' . trim(str_replace('/', '_', $path . '_' . $handle), '_') . '.xsl');
		}*/
		
		private function __buildDatasourcePooledParamList($datasources){
			if(!is_array($datasources) || empty($datasources)) return array();
			
			$list = array();
			
			foreach($datasources as $handle){
				$rootelement = str_replace('_', '-', $handle);
				$list[] = '$ds-' . $rootelement;
			}
			
			return $list;		
		}
		
		private function __findDatasourceOrder($dependenciesList){
			if(!is_array($dependenciesList) || empty($dependenciesList)) return;
			
			$orderedList = array();
			$dsKeyArray = $this->__buildDatasourcePooledParamList(array_keys($dependenciesList));

			## 1. First do a cleanup of each dependency list, removing non-existant DS's and find 
			##    the ones that have no dependencies, removing them from the list
			foreach($dependenciesList as $handle => $dependencies){
				
				$dependenciesList[$handle] = @array_intersect($dsKeyArray, $dependencies);
				
				if(empty($dependenciesList[$handle])){ 
					unset($dependenciesList[$handle]);
					$orderedList[] = str_replace('_', '-', $handle);
				}
			}


			## 2. Iterate over the remaining DS's. Find if all their dependencies are
			##    in the $orderedList array. Keep iterating until all DS's are in that list
			##	  or there are circular dependencies (list doesn't change between iterations of the while loop)
			do{
				
				$last_count = count($dependenciesList);

				foreach($dependenciesList as $handle => $dependencies){		
					
					$dependencies = array_map(create_function('$a', "return str_replace('\$ds-', NULL, \$a);"), $dependencies);
					
					foreach($dependencies as $d){
						foreach($orderedList as $o){
							if(!preg_match("/^{$o}/i", $d)) break;
							
							$orderedList[] = str_replace('_', '-', $handle);
							unset($dependenciesList[$handle]);
						}
					}
	
				}
								
			}while(!empty($dependenciesList) && $last_count > count($dependenciesList));
			
			if(!empty($dependenciesList)) $orderedList = array_merge($orderedList, array_keys($dependenciesList));
			
			return array_map(create_function('$a', "return str_replace('-', '_', \$a);"), $orderedList);
			
		}
		
		public function processDatasources($datasources, &$wrapper, array $params = array()) {
			//if (trim($datasources) == '') return;
			
			$datasources = preg_split('/,\s*/i', $datasources, -1, PREG_SPLIT_NO_EMPTY);
			$datasources = array_map('trim', $datasources);
			
			####
			# Delegate: FrontendProcessDataSources
			# Description: Manipulate the data source array and data source element wrapper
			# Global: Yes
			ExtensionManager::instance()->notifyMembers(
				'FrontendProcessDataSources', 
				'/frontend/', 
				array(
					'env' => $this->_env, 
					'datasources' => &$datasources, 
					'wrapper' => &$wrapper, 
					'page_data' => $this->_pageData
				)
			);
			
			if (!is_array($datasources) || empty($datasources)) return;
			#####

			$this->_env['pool'] = $params;
			$pool = $params;
			$dependencies = array();
			
			foreach ($datasources as $handle) {
				$pool[$handle] =& $this->DatasourceManager->create($handle, NULL, false);
				$dependencies[$handle] = $pool[$handle]->getDependencies();
				
				unset($ds);
			}
			
			$dsOrder = $this->__findDatasourceOrder($dependencies);
			
			foreach ($dsOrder as $handle) {
				$dbstats = Symphony::Database()->getStatistics();
				$queries = $dbstats['queries'];
				
				$ds = $pool[$handle];
				$ds->processParameters(array('env' => $this->_env, 'param' => $this->_param));
				
				if ($xml = $ds->grab($this->_env['pool'])) {
					if (is_object($xml)) $wrapper->appendChild($xml);
					else $wrapper->setValue(
						$wrapper->getValue() . PHP_EOL . '	' . trim($xml)
					);
				}
				
				$dbstats = Symphony::Database()->getStatistics();
				$queries = $dbstats['queries'] - $queries;
				
				unset($ds);
			}
		}
		
		private function __findEventOrder($a, $b){
			if ($a->priority() == $b->priority()) {
		        return 0;
		    }
		    return(($a->priority() > $b->priority()) ? -1 : 1);
		}
		
		private function processEvents($events, &$wrapper){
			
			####
			# Delegate: FrontendProcessEvents
			# Description: Manipulate the events array and event element wrapper
			# Global: Yes
			Extension::notify(
				'FrontendProcessEvents', 
				'/frontend/', 
				array(
					'env' => $this->_env, 
					'events' => &$events, 
					'wrapper' => &$wrapper, 
					'page_data' => $this->_pageData
				)
			);
			#####

			if(strlen(trim($events)) > 0){			
				$events = preg_split('/,\s*/i', $events, -1, PREG_SPLIT_NO_EMPTY);
				$events = array_map('trim', $events);
			
				if(!is_array($events) || empty($events)) return;
				
				$pool = array();
				foreach($events as $handle){
					$pool[$handle] = $this->EventManager->create($handle, array('env' => $this->_env, 'param' => $this->_param));
				}
				
				uasort($pool, array($this, '__findEventOrder'));
				
				foreach($pool as $handle => $event){
					
					$dbstats = Symphony::Database()->getStatistics();
					$queries = $dbstats['queries'];
	
					if($xml = $event->load()):
				
						if(is_object($xml)) $wrapper->appendChild($xml);
						else $wrapper->setValue($wrapper->getValue() . PHP_EOL . '	' . trim($xml));
										
					endif;
				
					$dbstats = Symphony::Database()->getStatistics();
					$queries = $dbstats['queries'] - $queries;
				
				}
			}
			
			####
			# Delegate: FrontendEventPostProcess
			# Description: Just after the page events have triggered. Provided with the XML object
			# Global: Yes
			Extension::notify('FrontendEventPostProcess', '/frontend/', array('xml' => &$wrapper));
			
		}		
	}
