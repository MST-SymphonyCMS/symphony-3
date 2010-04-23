<?php

	Class fieldCheckbox extends Field {
		function __construct(){
			parent::__construct();
			$this->_name = __('Checkbox');
		}

		public function canToggleData(){
			return ($this->{'required'} == 'no') ? true : false;
		}

		function allowDatasourceOutputGrouping(){
			return true;
		}

		function isSortable(){
			return true;
		}

		function canFilter(){
			return true;
		}

		public function canImport(){
			return true;
		}

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->{'element-name'} => array());

			foreach($records as $r){
				$data = $r->getData($this->{'id'});

				$value = $data->value;

				if(!isset($groups[$this->{'element-name'}][$handle])){
					$groups[$this->{'element-name'}][$handle] = array('attr' => array('value' => $value),
																		 'records' => array(), 'groups' => array());
				}

				$groups[$this->{'element-name'}][$value]['records'][] = $r;

			}

			return $groups;
		}

		public function getToggleStates(){
			return array('yes' => __('Yes'), 'no' => __('No'));
		}

		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$field_id = $this->{'id'};

			if ($andOperation) {
				foreach ($data as $value) {
					self::$key++;
					$value = $this->escape($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{self::$key}
							ON (e.id = t{$field_id}_{self::$key}.entry_id)
					";
					$where .= "
						AND (t{$field_id}_{self::$key}.value = '{$value})'
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
					AND (t{$field_id}_{self::$key}.value IN ('{$data}'))
				";
			}

			return true;
		}

		function displayDatasourceFilterPanel(&$wrapper, $data=NULL, $errors=NULL){

			parent::displayDatasourceFilterPanel($wrapper, $data, $errors);

			$existing_options = array('yes', 'no');

			if(is_array($existing_options) && !empty($existing_options)){
				$optionlist = Symphony::Parent()->Page->createElement('ul');
				$optionlist->setAttribute('class', 'tags');

				foreach($existing_options as $option) $optionlist->appendChild(Symphony::Parent()->Page->createElement('li', $option));

				$wrapper->appendChild($optionlist);
			}

		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $error, Entry $entry = null, $data = null) {
			if(!$data && $this->{'required'} == 'yes') {
				$value = null;
 			}
			else if(!$data){
				## TODO: Don't rely on $_POST
				if(isset($_POST) && !empty($_POST)) $value = 'no';
				elseif($this->{'default-state'} == 'on') $value = 'yes';
				else $value = 'no';
			}

			else $value = ($data->value == 'yes' ? 'yes' : 'no');

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->{'element-name'}.']', 'yes', 'checkbox', ($value == 'yes' ? array('checked' => 'checked') : array()));

			$label->appendChild($input);
			$label->appendChild(new DOMText(($this->{'description'} != NULL ? $this->{'description'} : $this->{'label'})));

			if (!is_null($error)) {
				$label = Widget::wrapFormElementWithError($label, $error['message']);
			}

			$wrapper->appendChild($label);
		}

		public function prepareTableValue($data, DOMElement $link=NULL){
			return ($data->value == 'yes' ? __('Yes') : __('No'));
		}

		public function processFormData($data, Entry $entry=NULL){
			$states = array('on', 'yes');

			if($this->{'required'} == 'yes' && !in_array(strtolower($data), $states)) {
				$data = null;
			}
			else $data = (in_array(strtolower($data), $states)) ? 'yes' : 'no';

   			return parent::processFormData($data, $entry);
		}

/*		Deprecated
		function commit(){

			if(!parent::commit()) return false;

			$field_id = $this->{'id'};
			$handle = $this->handle();

			if($field_id === false) return false;

			$fields = array(
				'field_id' => $field_id,
				'default-state' => ($this->{'default-state'} ? $this->{'default-state'} : 'off'),
				'description' => (trim($this->{'description'}) != '') ? $this->{'description'} : NULL
			);

			Symphony::Database()->delete('tbl_fields_' . $handle, array($field_id), "`field_id` = %d LIMIT 1");
			$field_id = Symphony::Database()->insert('tbl_fields_' . $handle, $fields);

			return ($field_id == 0 || !$field_id) ? false : true;
		}

*/
		public function findDefaultSettings(&$fields){
			if(!isset($fields['default-state'])) $fields['default-state'] = 'off';
		}

		public function displaySettingsPanel(SymphonyDOMElement $wrapper, MessageStack $messages) {
			parent::displaySettingsPanel($wrapper, $messages);

			$document = $wrapper->ownerDocument;

			// Long Description
			$label = Widget::Label(__('Long Description'));
			$label->appendChild($document->createElement('i', __('Optional')));
			$label->appendChild(Widget::Input('description', $this->{'description'}));
			$wrapper->appendChild($label);

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			// Default State
			$label = Widget::Label(__('Checked by default'));
			$input = Widget::Input('default-state', 'on', 'checkbox');

			if ($this->{'default-state'} == 'on') {
				$input->setAttribute('checked', 'checked');
			}

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$wrapper->appendChild($options_list);
		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					"CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` enum('yes','no') NOT NULL default '%s',
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					) TYPE=MyISAM;",
					$this->{'section'},
					$this->{'element-name'},
					($this->{'default-state'} == 'on' ? 'yes' : 'no')
				)
			);
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->{'label'});
			$label->appendChild(
				Widget::Input('fields['.$this->{'element-name'}.']', NULL, 'checkbox', ($this->{'default-state'} == 'on' ? array('checked' => 'checked') : array()))
			);

			return $label;
		}

	}

	return 'fieldCheckbox';