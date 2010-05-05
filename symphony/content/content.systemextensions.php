<?php

	require_once(LIB . '/class.administrationpage.php');

	Class contentSystemExtensions extends AdministrationPage{

		/*public function __call($name, $args){

			$type = NULL;

			switch($name){

				case '__viewIndex':
				case '__viewOverview':
					$this->buildPage(
						ExtensionManager::instance()->listAll()
					);
					return;
					break;

				case '__viewDatasources':
					$type = 'Data Source';
					break;

				case '__viewFields':
					$type = 'Field';
					break;

				case '__viewOther':
					$type = 'Other';
					break;

				default:
					return NULL;
					break;
			}

			if($type == 'Other'){
				$this->buildTable(
					ExtensionManager::instance()->listOthers(array('Data Source', 'Field'))
				);
			}
			else {
				$this->buildTable(
					ExtensionManager::instance()->listByType($type)
				);
			}
		}*/
		
		function view(){
		
		## Setup page
		
			$filter = ($this->_context[0] == 'type' || $this->_context[0] == 'status' ? $this->_context[0] : NULL);
			$value = $this->_context[1];

			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Extensions'))));
			$this->appendSubheading(__('Extensions'));

			$path = URL . '/symphony/system/extensions/';
			$this->Form->setAttribute('action', Administration::instance()->getCurrentPageURL());
			
			$layout = new Layout();
			$left = $layout->createColumn(Layout::SMALL);
			$left->setAttribute('class', 'column small filters');
			$right = $layout->createColumn(Layout::LARGE);
			
		## Process extensions and build lists
		
			$extensions = ExtensionManager::instance()->listAll();
			$lists = array();
			
			foreach($extensions as $e){
			/* Someone should look all this over. My thinking was that it'd be nice to only have
			to loop through the extensions once. Maybe that's stupid? */
			
			## Build lists by status
				switch($e['status']){
					case Extension::ENABLED:
						$lists['status']['enabled'][] = $e;
						break;
							
					case Extension::DISABLED:
						$lists['status']['disabled'][] = $e;
						break;
						
					case Extension::NOT_INSTALLED:
						$lists['status']['installable'][] = $e;
						break;
						
					case Extension::REQUIRES_UPDATE:
						$lists['status']['updateable'][] = $e;
				}
			
			## Build lists by type
				if(!empty($e['type'])){
					foreach($e['type'] as $t){
						if(!isset($lists['type'][$t])) {
							$lists['type'][$t][] = $e;
						}
						else {
							array_push($lists['type'][$t], $e);
						}
					}
				}
			}
			
		## Build status filter menu
		
			$h4 = $this->createElement('h4', __('Filter by Status'));
			$left->appendChild($h4);
			
			$ul = $this->createElement('ul');
			
			foreach($lists['status'] as $list => $extensions) {
				if(!empty($extensions)){
					$li = $this->createElement('li', Widget::Anchor(ucwords($list), $path . 'status/' . $list));
					if($value == $list){
						$li->setAttribute('class','active');
					}
					
					$count = $this->createElement('span', (string) count($extensions));

					$li->appendChild($count);
				
					$ul->appendChild($li);
				}
			}
	
			$left->appendChild($ul);
			
		## Build type filter menu
		
			$h4 = $this->createElement('h4', __('Filter by Type'));
			$left->appendChild($h4);
			
			$ul = $this->createElement('ul');
			
			foreach($lists['type'] as $list => $extensions) {
				if(!empty($extensions)){
					$li = $this->createElement('li', Widget::Anchor(ucwords($list), $path . 'type/' . $list));
					if($value == $list){
						$li->setAttribute('class','active');
					}
					
					$count = $this->createElement('span', (string) count($extensions));

					$li->appendChild($count);
				
					$ul->appendChild($li);
				}
			}
	
			$left->appendChild($ul);
			
		## Build table
			
			if(!is_null($filter) && !is_null($value)){
				$right->appendChild($this->buildTable($lists[$filter][$value]));
			}
			
		## Append table actions
			
			$tableActions = $this->createElement('div');
			$tableActions->setAttribute('class', 'actions');

			$options = array(
				array(NULL, false, __('With Selected...')),
				array('enable', false, __('Enable')),
				array('disable', false, __('Disable')),
				array('uninstall', false, __('Uninstall'), 'confirm'),
			);

			$tableActions->appendChild(Widget::Select('with-selected', $options));
			$tableActions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));

			$right->appendChild($tableActions);
		
			$layout->appendTo($this->Form);

		}

		function buildTable($extensions, $prefixes=false){
			
			## Sort by extensions name:
			uasort($extensions, array('ExtensionManager', 'sortByName'));

			$aTableHead = array(
				array(__('Name'), 'col'),
				array(__('Version'), 'col'),
				array(__('Author'), 'col'),
				array(__('Actions'), 'col')
			);

			$aTableBody = array();
			$colspan = count($aTableHead);

			if(!is_array($extensions) || empty($extensions)){
				$aTableBody = array(Widget::TableRow(
					array(
						Widget::TableData(__('None found.'), array(
								'class' => 'inactive',
								'colspan' => $colspan
							)
						)
					), array(
						'class' => 'odd'
					)
				));
			}

			else foreach($extensions as $name => $about){

				$fragment = $this->createDocumentFragment();

				if(!empty($about['table-link']) && $about['status'] == Extension::ENABLED) {

					$fragment->appendChild(
						Widget::Anchor($about['name'], Administration::instance()->getCurrentPageURL() . 'extension/' . trim($about['table-link'], '/'))
					);
				}
				else {
					$fragment->appendChild(
						new DOMText($about['name'])
					);
				}

				if($prefixes && isset($about['type'])) {
					$fragment->appendChild(
						$this->createElement('span', ' &middot; ' . $about['type'][0])
					);
				}

				## Setup each cell
				$td1 = Widget::TableData($fragment);
				$td2 = Widget::TableData($about['version']);

				$link = $about['author']['name'];

				if(isset($about['author']['website'])){
					$link = Widget::Anchor($about['author']['name'], General::validateURL($about['author']['website']));
				}

				elseif(isset($about['author']['email'])){
					$link = Widget::Anchor($about['author']['name'], 'mailto:' . $about['author']['email']);
				}

				$td3 = Widget::TableData($link);

				$td3->appendChild(Widget::Input('items['.$about['handle'].']', 'on', 'checkbox'));

				switch ($about['status']) {
					case Extension::ENABLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Disable'), '#'));
						break;

					case Extension::DISABLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Enable'), '#'));
						break;

					case Extension::NOT_INSTALLED:
						$td4 = Widget::TableData(Widget::Anchor(__('Install'), '#'));
						break;

					case Extension::REQUIRES_UPDATE:
						$td4 = Widget::TableData(Widget::Anchor(__('Update'), '#'));
				}

				## Add a row to the body array, assigning each cell to the row
				$aTableBody[] = Widget::TableRow(
					array($td1, $td2, $td3, $td4),
					($about['status'] == Extension::NOT_INSTALLED ? array('class' => 'inactive') : array())
				);
			}

			$table = Widget::Table(Widget::TableHead($aTableHead), NULL, Widget::TableBody($aTableBody));

			return $table;
		}

		function action(){
			$checked  = array_keys($_POST['items']);

			if(isset($_POST['with-selected']) && is_array($checked) && !empty($checked)){

				$action = $_POST['with-selected'];

				switch($action){

					case 'enable':

						## FIXME: Fix this delegate
						###
						# Delegate: Enable
						# Description: Notifies of enabling Extension. Array of selected services is provided.
						#              This can not be modified.
						//ExtensionManager::instance()->notifyMembers('Enable', getCurrentPage(), array('services' => $checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->enable($name) === false) return;
						}
						break;


					case 'disable':

						## FIXME: Fix this delegate
						###
						# Delegate: Disable
						# Description: Notifies of disabling Extension. Array of selected services is provided.
						#              This can be modified.
						//ExtensionManager::instance()->notifyMembers('Disable', getCurrentPage(), array('services' => &$checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->disable($name) === false) return;
						}
						break;

					case 'uninstall':

						## FIXME: Fix this delegate
						###
						# Delegate: Uninstall
						# Description: Notifies of uninstalling Extension. Array of selected services is provided.
						#              This can be modified.
						//ExtensionManager::instance()->notifyMembers('Uninstall', getCurrentPage(), array('services' => &$checked));

						foreach($checked as $name){
							if(ExtensionManager::instance()->uninstall($name) === false) return;
						}

						break;
				}

				redirect(Administration::instance()->getCurrentPageURL());
			}
		}

		/*function __viewDetail(){

			$date = Administration::instance()->getDateObj();

			if(!$extension_name = $this->_context[1]) redirect(ADMIN_URL . '/system/extensions/');

			if(!$extension = ExtensionManager::instance()->about($extension_name)) Administration::instance()->customError(E_USER_ERROR, 'Extension not found', 'The Symphony Extension you were looking for, <code>'.$extension_name.'</code>, could not be found.', 'Please check it has been installed correctly.');

			$link = $extension['author']['name'];

			if(isset($extension['author']['website']))
				$link = Widget::Anchor($extension['author']['name'], General::validateURL($extension['author']['website']));

			elseif(isset($extension['author']['email']))
				$link = Widget::Anchor($extension['author']['name'], 'mailto:' . $extension['author']['email']);

			$this->setPageType('form');
			$this->setTitle('Symphony &ndash; Extensions &ndash; ' . $extension['name']);
			$this->appendSubheading($extension['name']);

			$fieldset = new XMLElement('fieldset');

			$dl = new XMLElement('dl');

			$dl->appendChild(new XMLElement('dt', 'Author'));
			$dl->appendChild(new XMLElement('dd', (is_object($link) ? $link->generate(false) : $link)));

			$dl->appendChild(new XMLElement('dt', 'Version'));
			$dl->appendChild(new XMLElement('dd', $extension['version']));

			$dl->appendChild(new XMLElement('dt', 'Release Date'));
			$dl->appendChild(new XMLElement('dd', $date->get(true, true, strtotime($extension['release-date']))));

			$fieldset->appendChild($dl);

			$fieldset->appendChild((is_object($extension['description']) ? $extension['description'] : new XMLElement('p', strip_tags(General::sanitize($extension['description'])))));

			switch($extension['status']){

				case Extension::DISABLED:
				case Extension::ENABLED:
					$fieldset->appendChild(new XMLElement('p', '<strong>Uninstall this Extension, which will remove anything created by it, but will leave the original files intact. To fully remove it, you will need to manually delete the files.</strong>'));
					$fieldset->appendChild(Widget::Input('action[uninstall]', 'Uninstall Extension', 'submit'));
					break;

				case Extension::REQUIRES_UPDATE:
					$fieldset->appendChild(new XMLElement('p', '<strong>Note: This Extension is currently disabled as it is ready for updating. Use the button below to complete the update process.</strong>'));
					$fieldset->appendChild(Widget::Input('action[update]', 'Update Extension', 'submit'));
					break;

				case Extension::NOT_INSTALLED:
					$fieldset->appendChild(new XMLElement('p', '<strong>Note: This Extension has not been installed. If you wish to install it, please use the button below.</strong>'));
					$fieldset->appendChild(Widget::Input('action[install]', 'Install Extension', 'submit'));
					break;

			}

			$this->Form->appendChild($fieldset);
		}

		function __actionDetail(){

			if(!$extension_name = $this->_context[1]) redirect(ADMIN_URL . '/system/extensions/');

			if(!$extension = ExtensionManager::instance()->about($extension_name)) Administration::instance()->customError(E_USER_ERROR, 'Extension not found', 'The Symphony Extension you were looking for, <code>'.$extension_name.'</code>, could not be found.', 'Please check it has been installed correctly.');

			if(isset($_POST['action']['install']) && $extension['status'] == Extension::NOT_INSTALLED){
				ExtensionManager::instance()->enable($extension_name);
			}

			elseif(isset($_POST['action']['update']) && $extension['status'] == Extension::REQUIRES_UPDATE){
				ExtensionManager::instance()->enable($extension_name);
			}

			elseif(isset($_POST['action']['uninstall']) && in_array($extension['status'], array(Extension::ENABLED, Extension::DISABLED))){
				ExtensionManager::instance()->uninstall($extension_name);
			}
		}*/
	}
