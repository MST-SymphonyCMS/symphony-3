<?php

	Class fieldDate extends Field{

		const SIMPLE = 0;
		const REGEXP = 1;
		const RANGE = 3;
		const ERROR = 4;

		function __construct(){
			parent::__construct();
			$this->_name = __('Date');
		}

		public function create(){
			return Symphony::Database()->query(
				sprintf(
					'CREATE TABLE IF NOT EXISTS `tbl_data_%s_%s` (
						`id` int(11) unsigned NOT NULL auto_increment,
						`entry_id` int(11) unsigned NOT NULL,
						`value` varchar(80) default NULL,
						`local` int(11) default NULL,
						`gmt` int(11) default NULL,
						PRIMARY KEY  (`id`),
						KEY `entry_id` (`entry_id`),
						KEY `value` (`value`)
					)',
					$this->section,
					$this->{'element-name'}
				)
			);
		}

		function allowDatasourceOutputGrouping(){
			return true;
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

		function isSortable(){
			return true;
		}

		/*-------------------------------------------------------------------------
			Utilities:
		-------------------------------------------------------------------------*/

		protected function __buildSimpleFilterSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->id;

			if($andOperation):

				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
					$where .= " AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') = '".DateTimeObj::get('Y-m-d', strtotime($date))."' ";

					self::$key++;
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
				$where .= " AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d %H:%i:%s') IN ('".@implode("', '", $data)."') ";
				self::$key++;

			endif;


		}

		protected function __buildRangeFilterSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->id;

			if(empty($data)) return;

			if($andOperation):

				foreach($data as $date){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
					$where .= " AND (DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."'
								     AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";

					self::$key++;
				}

			else:

				$tmp = array();

				foreach($data as $date){

					$tmp[] = "(DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') >= '".DateTimeObj::get('Y-m-d', strtotime($date['start']))."'
								     AND DATE_FORMAT(`t$field_id".self::$key."`.value, '%Y-%m-%d') <= '".DateTimeObj::get('Y-m-d', strtotime($date['end']))."') ";
				}

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id".self::$key."` ON `e`.`id` = `t$field_id".self::$key."`.entry_id ";
				$where .= " AND (".@implode(' OR ', $tmp).") ";

				self::$key++;

			endif;

		}

		protected static function __cleanFilterString($string){
			$string = trim($string);
			$string = trim($string, '-/');

			return $string;
		}

		protected static function __parseFilter(&$string){

			$string = self::__cleanFilterString($string);

			## Check its not a regexp
			if(preg_match('/^regexp:/i', $string)){
				$string = str_replace('regexp:', '', $string);
				return self::REGEXP;
			}

			## Look to see if its a shorthand date (year only), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}$/i', $string)){
				$string = "$string-01-01 to $string-12-31";
			}

			## Human friendly terms
			elseif(preg_match('/^(equal to or )?(earlier|later) than (.*)$/i', $string, $match)){

				$string = $match[3];

				if(!self::__isValidDateString($string)) return self::ERROR;

				$time = strtotime($string);
				if($match[1] == "equal to or "){
					$later = DateTimeObj::get('Y-m-d H:i:s', $time);
					$earlier = $later;
				}
				else {
					$later = DateTimeObj::get('Y-m-d H:i:s', $time+1);
					$earlier = DateTimeObj::get('Y-m-d H:i:s', $time-1);
				}
				switch($match[2]){
					case 'later': $string = $later . ' to 2038-01-01'; break;
					case 'earlier': $string = '1970-01-03 to ' . $earlier; break;
				}

			}

			## Look to see if its a shorthand date (year and month), and convert to full date
			elseif(preg_match('/^(1|2)\d{3}[-\/]\d{1,2}$/i', $string)){

				$start = "{$string}-01";

				if(!self::__isValidDateString($start)) return self::ERROR;

				$string = "{$start} to {$string}-" . date('t', strtotime($start));
			}

			## Match for a simple date (Y-m-d), check its ok using checkdate() and go no further
			elseif(!preg_match('/to/i', $string)){

				if(preg_match('/^(1|2)\d{3}[-\/]\d{1,2}[-\/]\d{1,2}$/i', $string)){
					$string = "{$string} to {$string}";
				}

				else{
					if(!self::__isValidDateString($string)) return self::ERROR;

					$string = DateTimeObj::get('Y-m-d H:i:s', strtotime($string));
					return self::SIMPLE;
				}
			}

			## Parse the full date range and return an array

			if(!$parts = preg_split('/to/', $string, 2, PREG_SPLIT_NO_EMPTY)) return self::ERROR;

			$parts = array_map(array('self', '__cleanFilterString'), $parts);

			list($start, $end) = $parts;

			if(!self::__isValidDateString($start) || !self::__isValidDateString($end)) return self::ERROR;

			$string = array('start' => $start, 'end' => $end);

			return self::RANGE;
		}

		protected static function __isValidDateString($string){

			$string = trim($string);

			if(empty($string)) return false;

			## Its not a valid date, so just return it as is
			if(!$info = getdate(strtotime($string))) return false;
			elseif(!checkdate($info['mon'], $info['mday'], $info['year'])) return false;

			return true;
		}





		/*-------------------------------------------------------------------------
			Settings:
		-------------------------------------------------------------------------*/

		public function findDefaultSettings(array &$fields){
			if(!isset($fields['pre-populate'])) $fields['pre-populate'] = 'yes';
		}

		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);

			$document = $wrapper->ownerDocument;

			$options_list = $document->createElement('ul');
			$options_list->setAttribute('class', 'options-list');

			$this->appendShowColumnCheckbox($options_list);
			$this->appendRequiredCheckbox($options_list);

			$label = Widget::Label(__('Pre-populate this field with today\'s date'));
			$input = Widget::Input('pre-populate', 'yes', 'checkbox');
			if($this->{'pre-populate'} == 'yes') $input->setAttribute('checked', 'checked');

			$label->prependChild($input);
			$item = $document->createElement('li');
			$item->appendChild($label);
			$options_list->appendChild($item);

			$this->appendShowColumnCheckbox($options_list);

			$wrapper->appendChild($options_list);

		}

		/*-------------------------------------------------------------------------
			Publish:
		-------------------------------------------------------------------------*/

		public function prepareTableValue(StdClass $data, SymphonyDOMElement $link=NULL) {
			$value = null;

			if (isset($data->gmt) && !is_null($data->gmt)) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $data->gmt);
			}

			return parent::prepareTableValue((object)array('value' => $value), $link);
		}

		public function displayPublishPanel(SymphonyDOMElement $wrapper, MessageStack $errors, Entry $entry = null, $data = null){
			$name = $this->{'element-name'};
			$value = null;

			// New entry:
			if (is_null($data) && $this->{'pre-populate'} == 'yes') {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, null);
			}

			// Empty entry:
			else if (isset($data->gmt) && !is_null($data->gmt)) {
				$value = DateTimeObj::get(__SYM_DATETIME_FORMAT__, $data->gmt);
			}

			$label = Widget::Label($this->label, Widget::Input("fields[{$name}]", $value), array(
				'class' => 'date')
			);

			if ($errors->valid()){
				$label = Widget::wrapFormElementWithError($label, $errors->current()->message);
			}

			$wrapper->appendChild($label);
		}

		/*-------------------------------------------------------------------------
			Input:
		-------------------------------------------------------------------------*/

		public function processFormData($data, Entry $entry=NULL){

			if(isset($entry->data()->{$this->{'element-name'}})){
				$result = $entry->data()->{$this->{'element-name'}};
			}

			else {
				$result = (object)array(
					'value' => null,
					'local' => null,
					'gmt' => null
				);
			}

			if(is_null($data) || strlen(trim($data)) == 0){
				if ($this->{'pre-populate'} == 'yes') {
					$timestamp = strtotime(DateTimeObj::get('c', null));
				}
			}
			else{
				$timestamp = strtotime($data);
			}

			if(!is_null($timestamp)){
				$result->value = DateTimeObj::get('c', $timestamp);
				$result->local = strtotime(DateTimeObj::get('c', $timestamp));
				$result->gmt = strtotime(DateTimeObj::getGMT('c', $timestamp));
			}

			return $result;
		}

		public function validateData(MessageStack $errors, Entry $entry = null, $data = null) {

			if(empty($data)) return self::STATUS_OK;

			$message = NULL;

			if(!self::__isValidDateString($data)){
				$message = __("The date specified in '%s' is invalid.", array($this->label));
				return self::ERROR_INVALID;
			}

			return self::STATUS_OK;
		}

		/*-------------------------------------------------------------------------
			Output:
		-------------------------------------------------------------------------*/
		public function appendFormattedElement(&$wrapper, $data, $encode = false) {
			if (isset($data->gmt) && !is_null($data->gmt)) {
				$wrapper->appendChild(General::createXMLDateObject($wrapper->ownerDocument, $data->local, $this->{'element-name'}));
			}
		}

		public function getParameterPoolValue($data){
     		return DateTimeObj::get('Y-m-d H:i:s', $data->local);
		}

		/*-------------------------------------------------------------------------
			Filtering:
		-------------------------------------------------------------------------*/		
		
		public function provideFilterTypes() {
			return array(
				array('is', false, 'Is'),
				array('is-not', $data['type'] == 'is-not', 'Is not'),
				array('earlier than', $data['type'] == 'earlier-than', 'Earlier than'),
				array('earlier than or equalto', $data['type'] == 'earlier-than-or-equalto', 'Earlier than or equal to'),
				array('later than', $data['type'] == 'later-than', 'Later than'),
				array('later than or equalto', $data['type'] == 'later-than-or-equalto', 'Later than or equal to')
			);
		}		
		
		//	TODO: Revisit this.
		public function buildDSRetrivalSQL($filter, &$joins, &$where, $operation_type=DataSource::FILTER_OR) {

			self::$key++;

			$value = DataSource::prepareFilterValue($filter['value']);

			if(self::isFilterRegex($value)) return parent::buildDSRetrivalSQL($data, $joins, $where, $operation_type);

			$joins .= sprintf('
				LEFT OUTER JOIN `tbl_data_%2$s_%3$s` AS t%1$s ON (e.id = t%1$s.entry_id)
			', self::$key, $this->section, $this->{'element-name'});

			if ($operation_type == DataSource::FILTER_AND) {
				foreach ($value as $v) {
					$where .= sprintf(
						" AND (t%1\$s.value %2\$s '%3\$s') ",
						self::$key,
						$filter['type'] == 'is-not' ? '<>' : '=',
						$v
					);
				}

			}

			else {
				$where .= sprintf(
					" AND (t%1\$s.value %2\$s IN ('%3\$s')) ",
					self::$key,
					$filter['type'] == 'is-not' ? 'NOT' : NULL,
					implode("', '", $value)
				);
			}
/*
			if(self::isFilterRegex($data[0])) return parent::buildDSRetrivalSQL($data, $joins, $where, $andOperation);

			$parsed = array();

			foreach($data as $string){
				$type = self::__parseFilter($string);

				if($type == self::ERROR) return false;

				if(!is_array($parsed[$type])) $parsed[$type] = array();

				$parsed[$type][] = $string;
			}

			foreach($parsed as $type => $value){

				switch($type){

					case self::RANGE:
						$this->__buildRangeFilterSQL($value, $joins, $where, $andOperation);
						break;

					case self::SIMPLE:
						$this->__buildSimpleFilterSQL($value, $joins, $where, $andOperation);
						break;

				}
			}
*/
			return true;
		}


		/*-------------------------------------------------------------------------
			Grouping:
		-------------------------------------------------------------------------*/

		public function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array('year' => array());

			foreach($records as $r){
				$data = $r->getData($this->id);

				$info = getdate($data['local']);

				$year = $info['year'];
				$month = ($info['mon'] < 10 ? '0' . $info['mon'] : $info['mon']);

				if(!isset($groups['year'][$year])) $groups['year'][$year] = array('attr' => array('value' => $year),
																				  'records' => array(),
																				  'groups' => array());

				if(!isset($groups['year'][$year]['groups']['month'])) $groups['year'][$year]['groups']['month'] = array();

				if(!isset($groups['year'][$year]['groups']['month'][$month])) $groups['year'][$year]['groups']['month'][$month] = array('attr' => array('value' => $month),
																				  					  'records' => array(),
																				  					  'groups' => array());


				$groups['year'][$year]['groups']['month'][$month]['records'][] = $r;

			}

			return $groups;

		}

	}

	return 'fieldDate';