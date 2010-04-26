<?php
	
	require_once(TOOLKIT . '/class.entry.php');
	
	Class SectionsDataSource extends DataSource {

		public function __construct(){
			// Set Default Values
			$this->_about = new StdClass;
			$this->_parameters = (object)array(
			   'root-element' => NULL,
			   'limit' => 20,
			   'page' => 1,
			   'section' => NULL,
			   'conditions' => array(),
			   'filter' => array(),
			   'redirect-404-on-empty' => false,
			   'sort-field' => 'system:id',
			   'sort-order' => 'desc',
			   'included-elements' => array(),
			   'parameter-output' => array(),
			);
		}

		final public function type(){
			return 'ds_sections';
		}

		public function template(){
			return EXTENSIONS . '/ds_sections/templates/datasource.php';
		}

		public function save(MessageStack &$errors){

			if (strlen(trim($this->parameters()->limit)) == 0 || (is_numeric($this->parameters()->limit) && $this->parameters()->limit < 1)) {
				$errors->append('limit', __('A result limit must be set'));
			}

			if (strlen(trim($this->parameters()->page)) == 0 || (is_numeric($this->parameters()->page) && $this->parameters()->page < 1)) {
				$errors->append('page', __('A page number must be set'));
			}

			return parent::save($errors);
		}

		/*public function canAppendAssociatedEntryCount() {
			return false;
		}

		public function canAppendPagination() {
			return false;
		}

		public function canHTMLEncodeText() {
			return false;
		}

		public function canRedirectOnEmpty() {
			return false;
		}

		public function getFilters() {
			return array();
		}

		public function getGroupField() {
			return '';
		}

		public function getIncludedElements() {
			return array();
		}

		public function getOutputParams() {
			return array();
		}

		public function getPaginationLimit() {
			return '20';
		}

		public function getPaginationPage() {
			return '1';
		}

		public function getRequiredURLParam() {
			return '';
		}

		public function getRootElement() {
			return 'sections';
		}

		public function getSection() {
			return null;
		}

		public function getSortField() {
			return 'system:id';
		}

		public function getSortOrder() {
			return 'desc';
		}*/

		public function render(Register &$ParameterOutput){
			$execute = true;

			$result = new XMLDocument;
			$result->appendChild($result->createElement($this->parameters()->{'root-element'}));
			
			$root = $result->documentElement;

			//	Conditions
			//	If any one condtion returns true (that is, do not execute), the DS will not execute at all
			if(is_array($this->parameters()->conditions)) {
				foreach($this->parameters()->conditions as $condition) {
					$c = Datasource::resolveParameter($condition['parameter'], $ParameterOutput);

					// Is Empty
					if($condition['logic'] == 'empty' && (is_null($c) || strlen($c) == 0)){
						$execute = false;
					}
					
					// Is Set
					elseif($condition['logic'] == 'set' && !is_null($c)){
						$execute = false;
					}
					
					if($execute !== true) {
						return NULL;
					}
					
				}
			}


			//	Process Datasource Filters for each of the Fields
			if(is_array($this->parameters()->filters) && !empty($this->parameters()->filters)) {

			}
			
			// Grab the section
			try{
				$section = Section::loadFromHandle($this->parameters()->section);
			}
			catch(SectionException $e){
				
			}
			catch(Exception $e){
				
			}
			
			$pagination = (object)array(
				'total-entries' => NULL,
				'entries-per-page' => max(1, (int)$this->replaceParametersInString($this->parameters()->limit, $ParameterOutput)),
				'total-pages' => NULL,
				'current-page' => max(1, (int)$this->replaceParametersInString($this->parameters()->page, $ParameterOutput)),
			);
		
			$pagination->{'record-start'} = max(0, ($pagination->{'current-page'} - 1) * $pagination->{'entries-per-page'});
			
			$order = $joins = $where = NULL;

			//	Apply the Sorting & Direction
			//	Apply the limiting

			//	If count of result is 0 && redirect to 404 is true, throw FrontendException

			//	Inject any Output Params into the Register through ParameterOutput

			//	If any of the system: mode fields are called, append them to the front of the Datasource
			//	just after the root element.

			//	Foreach of the rows in the result, call appendFormattedElement

			//	Return a DOMDocument to the View::render function.

			/*
			$sort_field = $section->fetchFieldByHandle($section->{'publish-order-handle'});
			$sort_field->buildSortingSQL($joins, $order, $section->{'publish-order-direction'});
			*/
			
			$query = sprintf(
				'SELECT SQL_CALC_FOUND_ROWS e.* FROM `tbl_entries` AS `e` %1$s WHERE `section` = "%2$s" %3$s %4$s LIMIT %5$d, %6$d',
				$joins,
				$section->handle,
				$where,
				$order,
				$pagination->{'record-start'},
				$pagination->{'entries-per-page'}
			);
			
			try{
				$entries = Symphony::Database()->query($query, array(
						$section->handle,
						$section->{'publish-order-handle'}
					), 'EntryResult'
				);
			
				$pagination->{'total-entries'} = (int)Symphony::Database()->query("SELECT FOUND_ROWS() AS `total`")->current()->total;
				$pagination->{'total-pages'} = (int)ceil($pagination->{'total-entries'} * (1 / $pagination->{'entries-per-page'}));
				
				// Pagination Element
				$root->appendChild(General::buildPaginationElement(
					$result, $pagination->{'total-entries'}, $pagination->{'total-pages'}, $pagination->{'entries-per-page'}, $pagination->{'current-page'}
				));
				
				// Build Entry Records
				if($entries->length() > 0){
					
					// Do some pre-processing on the include-elements
					if(is_array($this->parameters()->{'included-elements'}) && !empty($this->parameters()->{'included-elements'})){
						$included_elements = (object)array('system' => array(), 'fields' => array());
						foreach($this->parameters()->{'included-elements'} as $element){
							if(preg_match('/^system:/i', $element)){
								$included_elements->system[] = preg_replace('/^system:/i', NULL, $element);
							}
							else{
								$parts = preg_split('/:/', $element, 2, PREG_SPLIT_NO_EMPTY);
								$included_elements->fields[] = array('element-name' => $parts[0], 'mode' => (isset($parts[1]) && strlen(trim($parts[1])) > 0 ? trim($parts[1]) : NULL));
							}
						}
					}
					
					foreach($entries as $e){
						
						// If there are included elements, need an entry element.
						if(is_array($this->parameters()->{'included-elements'}) && !empty($this->parameters()->{'included-elements'})){
							$entry = $result->createElement('entry');
							$entry->setAttribute('id', $e->id);
							$root->appendChild($entry);
							
							foreach($included_elements->system as $field){
								switch($field){
									case 'date':
										$entry->appendChild(General::createXMLDateObject($result, strtotime($entry->creation_date)));
										break;
										
									case 'user':
										$obj = User::load($e->user_id);
										$user = $result->createElement('user', $obj->getFullName());
										$user->setAttribute('id', $e->user_id);
										$user->setAttribute('username', $obj->username);
										$user->setAttribute('email-address', $obj->email);
										$entry->appendChild($user);
										break;
								}
							}
							
							foreach($included_elements->fields as $field){
								$section->fetchFieldByHandle($field['element-name'])->appendFormattedElement($entry, $e->data()->{$field['element-name']}, false, $field['mode']);
							}

						}
						
						if(is_array($this->parameters()->{'parameter-output'}) && !empty($this->parameters()->{'parameter-output'})){
						}
						
					}
				}

				// No Entries, Redirect
				elseif($this->parameters()->{'redirect-404-on-empty'} === true){
					throw new FrontendPageNotFoundException;
				}
				
				// No Entries, Show empty XML
				else{
					$this->emptyXMLSet($root);
				}
				
			}
			catch(DatabaseException $e){
				$root->appendChild($result->createElement(
					'error', General::sanitize($e->getMessage())
				));
			}
			
			return $result;

		}


		public function prepareSourceColumnValue(){
			$section = Section::loadFromHandle($this->_parameters->section);

			if ($section instanceof Section) {
				return Widget::TableData(
					Widget::Anchor($section->name, URL . '/symphony/blueprints/sections/edit/' . $section->handle . '/', array(
						'title' => $section->handle
					))
				);
			}

			else {
				return Widget::TableData(__('None'), array(
					'class' => 'inactive'
				));
			}
		}
	}
