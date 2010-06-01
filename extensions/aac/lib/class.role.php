<?php
	
	Class RoleException extends Exception{
	}
	
	Class RoleDatabaseResultIterator extends DBCMySQLResult{
		public function current(){
			$record = parent::current();

			$role = new Role;
			$role->id = $record->id;
			$role->name = $record->name;
			$role->description = $record->description;
			
			return $role;
		}
	}
	
	Class RoleIterator implements Iterator{

	    private $rows;

	    public function __construct(){
			$this->rows = Symphony::Database()->query(
				"SELECT * FROM `tbl_aac_roles` ORDER BY `name` ASC", array(), 
				'RoleDatabaseResultIterator'
			);
	    }

	    public function rewind(){
	        $this->rows->rewind();
	    }

	    public function current(){
	        return $this->rows->current();
	    }

	    public function key(){
	        return $this->rows->key();
	    }

	    public function next(){
	        return $this->rows->next();
	    }

	    public function valid(){
	        return $this->rows->valid();
	    }

		public function length(){
			return $this->rows->length();
		}

	}
	
	Class Role{
		
		private $fields;
		private $users;
		
		public function __construct(){
			$this->fields = array();
		}
		
		public static function load($id){
			return Symphony::Database()->query(
				"SELECT * FROM `tbl_aac_roles` WHERE `id` = %d LIMIT 1", 
				array($id), 
				'RoleDatabaseResultIterator'
			)->current();
		}
		
		public static function save(self $role, MessageStack &$errors){
			// Validation
			if(strlen(trim($role->name)) == 0){
				$errors->append('name', __('Name is required.'));
			}
			elseif(
				Symphony::Database()->query("SELECT `id` FROM `tbl_aac_roles` WHERE `name` = '%s' %s",
				array(
					$role->name,
					(isset($role->id) ? "AND `id` != {$role->id} " : NULL)
				))->length() > 0
			){
				$errors->append('name', __('A role with that name already exists.'));
			}
			
			
			if($errors->length() > 0){
				throw new RoleException('Errors were encountered whist attempting to save.');
			}
			
			// Saving
			return Symphony::Database()->insert(
				'tbl_aac_roles',
				array(
					'id' => $role->id,
					'name' => $role->name,
					'description' => $role->description
				),
				Database::UPDATE_ON_DUPLICATE
			);
		}
		
		public function users(){
			return Symphony::Database()->query(
				"SELECT * FROM `tbl_users` WHERE `role_id` = %d ORDER BY `id` ASC", array($this->id), 'UserResult'
			);
		}
		
		public function __get($name){
			if(!isset($this->fields[$name]) || strlen(trim($this->fields[$name])) == 0) return NULL;
			return $this->fields[$name];
		}
		
		public function __set($name, $value){
			$this->fields[trim($name)] = $value;
		}
		
		public function __isset($name){
			return isset($this->fields[$name]);
		}
		
		public static function moveUsers($source_role_id, $destination_role_id){
			Symphony::Database()->update('tbl_users', array('role_id' => $destination_role_id), array(), sprintf('`role_id` = %d', (int)$source_role_id));
		}
		
		public static function delete($role_id, $replacement_role_id=NULL){
			if(!is_null($replacement_role_id) && is_numeric($replacement_role_id)){
				self::moveUsers($role_id, $replacement_role_id);
			}
			
			if(self::load($role_id)->users()->length() > 0){
				throw new RoleException(__('Cannot delete a role that contains users. Please move users to an existing role first.'));
			}

			Symphony::Database()->delete('tbl_aac_roles', (array)$role_id, '`id` = %d');
		}
		
	}
	