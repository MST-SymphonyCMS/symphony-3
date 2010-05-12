<?php

	require_once 'lib/class.datasource.php';
		
	class Extension_DS_StaticXML implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Static XML',
				'version'		=> '1.0.0',
				'release-date'	=> '2010-02-26',
				'type'			=> array(
					'Data Source', 'Core'
				),
				'author'		=> (object)array(
					'name'			=> 'Symphony Team',
					'website'		=> 'http://symphony-cms.com/',
					'email'			=> 'team@symphony-cms.com'
				),
				'provides'		=> array(
					'datasource_template'
				),
				'description'	=> 'Create data sources from an XML string.'
			);
		}
		
		public function prepareDatasource(DataSource $datasource = null, array $data = null) {
			if (is_null($datasource)) {
				$datasource = new StaticXMLDataSource;
			}
			
			$datasource->prepare($data);
			
			return $datasource;
		}

		public function viewDatasource(DataSource $datasource, SymphonyDOMElement $wrapper, MessageStack $errors) {
			$datasource->view($wrapper, $errors);
		}
	}
	
	return 'Extension_DS_StaticXML';
