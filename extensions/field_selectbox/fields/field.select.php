<?php

	Class fieldSelect extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('Select Box');
		}

		public function canToggleData(){
			return !isset($this->{'allow-multiple-selection'}) ? true : false;
		}

		function allowDatasourceOutputGrouping(){
			## Grouping follows the same rule as toggling.
			return $this->canToggleData();
		}

		function allowDatasourceParamOutput(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		function canPrePopulate(){
			return true;
		}

		function isSortable(){
			return true;
		}

		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (!is_array($data) or empty($data)) return;

			$list = Symphony::Parent()->Page->createElement($this->{'element-name'});

			if (!is_array($data['handle']) and !is_array($data['value'])) {
				$data = array(
					'handle'	=> array($data['handle']),
					'value'		=> array($data['value'])
				);
			}

			foreach ($data['value'] as $index => $value) {
				$list->appendChild(Symphony::Parent()->Page->createElement(
					'item',
					General::sanitize($value),
					array(
						'handle'	=> $data['handle'][$index]
					)
				));
			}

			$wrapper->appendChild($list);
		}

		function fetchAssociatedEntrySearchValue($data){
			if(!is_array($data)) return $data;

			return $data['value'];
		}

		function fetchAssociatedEntryCount($value){
			$result = Symphony::Database()->query("
				SELECT
					`entry_id`
				FROM
					`tbl_entries_data_%d`
				WHERE
					`value` = '%s
				",
				$this->id,
				$value
			);

			return ($result->valid()) ? $result->current->count : false;
		}

		function fetchAssociatedEntryIDs($value){
			$result = Symphony::Database()->query("
				SELECT
					count(*) AS `count`
				FROM
					`tbl_entries_data_%d`
				WHERE
					`value` = '%s
				",
				$this->id,
				$value
			);

			return ($result->valid()) ? $result->resultColumn('entry_id') : false;
		}

		public function getToggleStates() {
			$values = preg_split('/,\s*/i', $this->{'static-options'}, -1, PREG_SPLIT_NO_EMPTY);

			if ($this->{'dynamic-options'} != '') $this->findAndAddDynamicOptions($values);

			$values = array_map('trim', $values);
			$states = array();

			foreach ($values as $value) {
				$states[$value] = $value;
			}

			return $states;
		}

		function toggleEntryData(StdClass $data, $value, Entry $entry=NULL){
			$data['value'] = $newState;
			$data['handle'] = Lang::createHandle($newState);
			return $data;
		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $error, Entry $entry = null, $data = null) {
			if(!is_array($data)){
				$data = array($data);
			}

			$selected = array();
			foreach($data as $d){
				if(!($d instanceof StdClass) || !isset($d->value)) continue;
				$selected[] = $d->value;
			}

			$states = $this->getToggleStates();
			natsort($states);

			$options = array();

			if($this->{'required'} == 'yes') {
				$options[] = array(null, false);
			}

			foreach($states as $handle => $v){
				$options[] = array(General::sanitize($v), in_array($v, $selected), General::sanitize($v));
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Select($fieldname, $options,
				($this->{'allow-multiple-selection'} == 'yes') ? array('multiple' => 'multiple') : array()
			));

			if (!is_null($error)) {
				$label = Widget::wrapFormElementWithError($label, $error['message']);
			}

			$wrapper->appendChild($label);
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL){

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors);

			$data = preg_split('/,\s*/i', $data);
			$data = array_map('trim', $data);

			$existing_options = $this->getToggleStates();

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = Symphony::Parent()->Page->createElement('ul');
				$optionlist->setAttribute('class', 'tags');

				foreach($existing_options as $option)
					$optionlist->appendChild(
						Symphony::Parent()->Page->createElement('li', $option)
					);

				$wrapper->appendChild($optionlist);
			}

		}

		function findAndAddDynamicOptions(&$values){
			list($section, $field_handle) = explode("::", $this->{'dynamic-options'});

			if(!is_array($values)) $values = array();

			$result = Symphony::Database()->query("
				SELECT
					DISTINCT `value`
				FROM
					`tbl_data_%s_%s`
				", array($section, $field_handle)
			);

			if($result->valid()) $values = array_merge($values, $result->resultColumn('value'));
		}

		public function prepareTableValue($data, DOMElement $link=NULL){

			if(!is_array($data)){
				$data = array($data);
			}

			$values = array();
			foreach($data as $d){
				$values[] = $d->value;
			}

			return parent::prepareTableValue((object)array('value' => General::sanitize(implode(', ', $values))), $link);
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->id;

			if (self::isFilterRegex($data[0])) {
				self::$key++;
				$pattern = str_replace('regexp:', '', $this->escape($data[0]));
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{self::$key}.value REGEXP '{$pattern}'
						OR t{$field_id}_{self::$key}.handle REGEXP '{$pattern}'
					)
				";

			} elseif ($andOperation) {
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{self::$key}.value = '{$value}'
							OR t{$field_id}_{self::$key}.handle = '{$value}'
						)
					";
				}

			} else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
					$value = $this->escape($value);
				}

				self::$key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
						ON (e.id = t{$field_id}_{self::$key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{self::$key}.value IN ('{$data}')
						OR t{$field_id}_{self::$key}.handle IN ('{$data}')
					)
				";
			}

			return true;
		}

		public function processFormData($data, Entry $entry=NULL){

			//if(isset($entry->data()->{$this->{'element-name'}})){
			//	$result = $entry->data()->{$this->{'element-name'}};
			//}

			//else {
				$result = (object)array(
					'value' => null,
					'handle' => null
				);
			//}

			if(!is_null($data)){
				$result->value = $data;
				$result->handle = Lang::createHandle($data);
			}

			return $result;
		}

/*
		Deprecated

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::STATUS_OK;

			if(!is_array($data)) return array('value' => $data, 'handle' => Lang::createHandle($data));

			if(empty($data)) return NULL;

			$result = array('value' => array(), 'handle' => array());

			foreach($data as $value){
				$result['value'][] = $value;
				$result['handle'][] = Lang::createHandle($value);
			}

			return $result;
		}

		function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->id;
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'static-options' => ($this->{'static-options'} != '') ? $this->{'static-options'} : NULL,
				'dynamic-options' => ($this->{'dynamic-options'} != '') ? $this->{'dynamic-options'} : NULL,
				'allow-multiple-selection' => ($this->{'allow-multiple-selection'} ? $this->{'allow-multiple-selection'} : 'no')
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$f_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			if ($f_id == 0 || !$f_id) return false;

			$this->removeSectionAssociation($field_id);
			$this->createSectionAssociation(NULL, $field_id, $this->{'dynamic-options'});

			return true;

		}
*/

		public function validateSettings(MessageStack $messages, $checkForDuplicates=true){
			if ($this->{'static-options'} == '' && ($this->{'dynamic-options'} == '' || $this->{'dynamic-options'} == 'none')) {
				$messages->{'dynamic-options'} = __('At least one source must be specified, dynamic or static.');
			}

			return parent::validateSettings($messages, $checkForDuplicates);
		}

		public function findDefaultSettings(array &$fields){
			if(!isset($fields['allow-multiple-selection'])) $fields['allow-multiple-selection'] = 'no';
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
			parent::displaySettingsPanel($wrapper, $messages);

			$document = $wrapper->ownerDocument;

			$label = Widget::Label(__('Static Options'));
			$label->appendChild($document->createElement('i', __('Optional')));
			$input = Widget::Input('static-options', General::sanitize($this->{'static-options'}));
			$label->appendChild($input);
			$wrapper->appendChild($label);

			$label = Widget::Label(__('Dynamic Options'));

			$options = array(
				array('', false, __('None')),
			);

			foreach (new SectionIterator as $section) {
				if(!is_array($section->fields) || $section->handle == $document->_context[1]) continue;

				$fields = array();

				foreach($section->fields as $field) {
					if($field->canPrePopulate()) {
						$fields[] = array(
							$section->handle . '::' .$field->{'element-name'},
							($this->{'dynamic-options'} == $section->handle . '::' .$field->{'element-name'}),
							$field->label
						);
					}
				}

				if(!empty($fields)) {
					$options[] = array(
						'label' => $section->name,
						'options' => $fields
					);
				}
			}

			$label->appendChild(Widget::Select('dynamic-options', $options));

			if(isset($errors['dynamic-options'])) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $errors['dynamic-options']));
			else $wrapper->appendChild($label);

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			## Allow selection of multiple items
			$label = Widget::Label(__('Allow selection of multiple options'));

			$input = Widget::Input('allow-multiple-selection', 'yes', 'checkbox');
			if($this->{'allow-multiple-selection'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$options_list->appendChild($label);

			$wrapper->appendChild($options_list);

		}

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->{'element-name'} => array());

			foreach($records as $r){
				$data = $r->getData($this->id);

				$value = $data['value'];
				$handle = Lang::createHandle($value);

				if(!isset($groups[$this->{'element-name'}][$handle])){
					$groups[$this->{'element-name'}][$handle] = array('attr' => array('handle' => $handle, 'value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->{'element-name'}][$handle]['records'][] = $r;

			}

			return $groups;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {

			if(!is_array($data)){
				$data = array($data);
			}

			$value = NULL;
			foreach($data as $d){
				$value .= $d->value;
			}
			
			// TODO: Isn't calling processFormData a prerequisit to callid validateData?
			return parent::validateData($errors, $entry, $this->processFormData($value, $entry));
		}

		/*	Possibly could be removed.. */
		public function saveData(MessageStack $errors, Entry $entry, $data = null) {

			// Since we are dealing with multiple
			// values, must purge the existing data first
			Symphony::Database()->delete(
				sprintf('tbl_data_%s_%s', $entry->section, $this->{'element-name'}),
				array($entry->id),
				"`entry_id` = %s"
			);

			if(!is_array($data)){
				$data = array($data);
			}
			foreach($data as $d){
				parent::saveData($d, $errors, $entry);
			}
			return Field::STATUS_OK;
		}

		public function loadDataFromDatabase(Entry $entry, $expect_multiple = false) {
			return parent::loadDataFromDatabase($entry, true);
		}

		public function createTable(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`handle` varchar(255) default NULL,
						`value` varchar(255) default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `handle` (`handle`),
						KEY `value` (`value`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		public function getExampleFormMarkup(){
			$states = $this->getToggleStates();

			$options = array();

			foreach($states as $handle => $v){
				$options[] = array($v, NULL, $v);
			}

			$fieldname = 'fields['.$this->{'element-name'}.']';
			if($this->{'allow-multiple-selection'} == 'yes') $fieldname .= '[]';

			$label = Widget::Label($this->label);
			$label->appendChild(Widget::Select($fieldname, $options,
				($this->{'allow-multiple-selection'} == 'yes') ? array('multiple' => 'multiple') : array()
			));

			return $label;
		}

	}

	return 'fieldSelect';