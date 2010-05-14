<?php

	require_once 'lib/class.datasource.php';

	Class Extension_DS_Users implements iExtension {
		public function about() {
			return (object)array(
				'name'			=> 'Users DataSource',
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
				'description'	=> 'Create data sources from backend user data.'
			);
		}
		
	/*-------------------------------------------------------------------------
		DataSources:
	-------------------------------------------------------------------------*/
		
		public function getDataSourceTypes() {
			return array(
				(object)array(
					'class'		=> 'UsersDataSource',
					'name'		=> __('Users')
				)
			);
		}
	}
	
	return 'Extension_DS_Users';