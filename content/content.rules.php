<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	
	class contentExtensionRedirection_ManagerRules extends AdministrationPage {
		protected $_driver = null;
		protected $_editing = false;
		protected $_errors = array();
		protected $_fields = null;
		protected $_pagination = null;
		protected $_status = null;
		protected $_table_column = 'name';
		protected $_table_columns = array();
		protected $_table_direction = 'asc';
		protected $_rules = array();
		protected $_uri = null;
		protected $_valid = true;
		
		public function __construct(&$parent){
			parent::__construct($parent);
			
			$this->_uri = URL . '/symphony/extension/redirection_manager';
			$this->_driver = $this->_Parent->ExtensionManager->create('redirection_manager');
		}
		
		public function build($context) {
			if (@$context[0] == 'edit' or @$context[0] == 'new') {
				$this->_editing = $context[0] == 'edit';
				$this->_status = $context[2];
				
				if ($this->_editing) {
					$this->_fields = $this->_driver->getRule($context[1]);
				}
				
			} else {
				$this->__prepareIndex();
			}
			
			parent::build($context);
		}
		
	/*-------------------------------------------------------------------------
		Edit
	-------------------------------------------------------------------------*/
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __actionEdit() {
			if (@array_key_exists('delete', $_POST['action'])) {
				$this->__actionEditDelete();
				
			} else {
				$this->__actionEditNormal();
			}
		}
		
		public function __actionEditDelete() {
			$this->_Parent->Database->delete('tbl_redirectionmanager_rules', " `id` = '{$this->_fields['id']}'");
			
			redirect($this->_uri . '/rules/');
		}
		
		public function __actionEditNormal() {
			$this->_fields = (isset($_POST['fields']) ? $_POST['fields'] : $this->_fields);
			
		// Validate -----------------------------------------------------------
	
			if (empty($this->_fields['name'])) {
				$this->_errors['name'] = 'Name must not be empty.';
			}
			if (empty($this->_fields['source'])) {
				$this->_errors['source'] = 'Source must not be empty.';
			}
			if (empty($this->_fields['target'])) {
				$this->_errors['target'] = 'Target must not be empty.';
			}
			if (!isset($this->_fields['method'])) {
				$this->_fields['method'] = 'url';
			}
			if (!empty($this->_errors)) {
				$this->_valud = false;
				return;
			}
			
		// Save ---------------------------------------------------------------
			$fields  = $this->processRawFieldData($this->_fields);
			$result = Symphony::Database()->insert($fields, 'tbl_redirectionmanager_rules', true);
			
			if (!$this->_editing) {
				$redirect_mode = 'created';
				$rule_id = (integer)$this->_Parent->Database->fetchVar('id', 0, "
					SELECT
						p.id
					FROM
						`tbl_redirectionmanager_rules` AS p
					ORDER BY
						p.id DESC
					LIMIT 1
				");
				
			} else {
				$redirect_mode = 'saved';
				$rule_id = $this->_fields['id'];
			}
			
			
			redirect("{$this->_uri}/rules/edit/{$rule_id}/{$redirect_mode}/");
		}
		
		public function __viewNew() {
			self::__viewEdit();
		}
		
		public function __viewEdit() {
			$this->setPageType('form');
			$title = ($this->_editing ? $this->_fields['name'] : 'Untitled');
			$this->setTitle("Symphony &ndash; Redirection Manager &ndash; {$title}");
			$this->appendSubheading("<a href=\"{$this->_uri}/rules/\">Rules</a> &mdash; {$title}");
			
			if (!$this->_valid) $this->pageAlert('
				An error occurred while processing this form.
				<a href="#error">See below for details.</a>',
				AdministrationPage::PAGE_ALERT_ERROR
			);
			
			// Status message:
			if ($this->_status) {
				$action = null;
				
				switch ($this->_status) {
					case 'saved': $action = 'updated'; break;
					case 'created': $action = 'created'; break;
				}
				
				if ($action) {
					Administration::instance()->Page->pageAlert(
						__('Rule '.$action.' succesfully.') . ' <a href="' . URL . 'extension/redirectionmanager/rules/new/'.'">Create another?</a>',
						Alert::SUCCESS
					);
				}
				$this->pageAlert(
					__(
						$action, array(
							__('Template'), 
							DateTimeObj::get(__SYM_TIME_FORMAT__), 
							URL . '/symphony/extension/emailtemplatefilter/templates/new/', 
							URL . '/symphony/extension/emailtemplatefilter/templates/',
							__('Templates')
						)
					),
					Alert::SUCCESS
				);
				
				
			}
			
		// Fields -------------------------------------------------------------
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', 'Essentials'));
			
			if (!empty($this->_fields['id'])) {
				$fieldset->appendChild(Widget::Input("fields[id]", $this->_fields['id'], 'hidden'));
			}
			
		// Title --------------------------------------------------------------
			
			$label = Widget::Label('Name');
			$label->appendChild(Widget::Input(
				'fields[name]',
				General::sanitize($this->_fields['name'])
			));
			
			if (isset($this->_errors['name'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['name']);
			}
			
			$fieldset->appendChild($label);
			
			
		// Source --------------------------------------------------------------
			
			$label = Widget::Label('Source');
			
			if (isset($_GET["source"])) {
				$label->appendChild(Widget::Input(
					'fields[source]',
					General::sanitize($_GET["source"])
				));
			} else {
				$label->appendChild(Widget::Input(
					'fields[source]',
					General::sanitize($this->_fields['source'])
				));
			}
			
			if (isset($this->_errors['source'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['source']);
			}
		
			
			
			$fieldset->appendChild($label);
			
			
		// Target --------------------------------------------------------------
			
			$label = Widget::Label('Target');
			$label->appendChild(Widget::Input(
				'fields[target]',
				General::sanitize($this->_fields['target'])
			));
			
			if (isset($this->_errors['target'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['target']);
			}
			
			$fieldset->appendChild($label);
	
			
		// Method -------------------------------------------------------------
			
			$group = new XMLElement('div');
			$group->setAttribute('class', 'redirectionmanager_group');
			
			$input = Widget::Input(
				'fields[method]', 'regexp', 'checkbox',
				($this->_fields['method'] == 'regexp' ? array('checked' => 'checked') : null)
			);
			$input = $input->generate();
			
			$label = Widget::Label("{$input} Use regular expressions?");
			
			$group->appendChild($label);
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue("
				Use * as a wild-card unless regular expressions are enabled.
			");
			
			$group->appendChild($help);
			$fieldset->appendChild($group);
			$this->Form->appendChild($fieldset);
			
			
		// Type --------------------------------------------------------------	
			
			$options = array(
				array('301'),
				array('302'),
				array('307')
			);
		
			$label = Widget::Label('HTTP Type');
			$label->appendChild(Widget::select(
				'fields[type]', $options
				
			));
			
			if (isset($this->_errors['type'])) {
				$label = Widget::wrapFormElementWithError($label, $this->_errors['type']);
			}
			
			$fieldset->appendChild($label);
			
		// Save ---------------------------------------------------------------
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input(
					'action[save]', 'Save Changes', 'submit',
					array(
						'accesskey'		=> 's'
					)
				)
			);
			
		// Delete -------------------------------------------------------------
			
			if ($this->_editing) {
				$button = new XMLElement('button', 'Delete');
				$button->setAttributeArray(array(
					'name'		=> 'action[delete]',
					'class'		=> 'confirm delete',
					'title'		=> 'Delete this email'
				));
				$div->appendChild($button);
			}
			
			$this->Form->appendChild($div);
		}
	
	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/
		
		public function processRawFieldData($data) {
			if (!is_array($data)) $data = array($data);
			
			if (empty($data)) return null;
			$result = array();
			
			$result = array(
				'id'		=> @$data['id'],
				'name'		=> @$data['name'],
				'type'	    => @$data['type'],
				'source'    => @$data['source'],
				'source_compiled'	=> null,
				'target'    => @$data['target'],
				'source_compiled'	=> null,
				'method'	=> @$data['method']
			);

			if ($result['method'] != 'regexp') {

				$choices = preg_split('/\s*,\s*/', $result['source'], -1, PREG_SPLIT_NO_EMPTY);
				foreach ($choices as $index => $choice) {
					if ($index) $expression_source .= '|';
					
					$expression_source .= str_replace(
						'\\*', '.*?',
						preg_quote($choice, '/')
					);
				}
				
				$expression_source = "^{$expression_source}$";
				
				
				$choices = preg_split('/\s*,\s*/', $result['target'], -1, PREG_SPLIT_NO_EMPTY);
				foreach ($choices as $index => $choice) {
					if ($index) $expression_target .= '|';
					
					$expression_target.= str_replace(
						'\\*', '.*?',
						preg_quote($choice, '/')
					);
				}
				$expression_target = "^{$expression_target}$";	
			}
			
			else {
				$expression_source = str_replace('/', '\\/', $result['source']);
				$expression_target = str_replace('/', '\\/', $result['target']);
			}
			
			$result['source_compiled'] = "/{$expression_source}/";
			$result['target_compiled'] = "/{$expression_target}/";
			
			return $result;
		}
	
	
		
	/*-------------------------------------------------------------------------
		Index
	-------------------------------------------------------------------------*/
		
		public function __prepareIndex() {
			$this->_table_columns = array(
				'name'			=> array('Name', true),
				'type'			=> array('Type', true),
				'source'	=> array('Source', true),
				'target'	=> array('Target', true),
			);
			
			if (@$_GET['sort'] and $this->_table_columns[$_GET['sort']][1]) {
				$this->_table_column = $_GET['sort'];
			}
			
			if (@$_GET['order'] == 'desc') {
				$this->_table_direction = 'desc';
			}
			
			$this->_pagination = (object)array(
				'page'		=> (@(integer)$_GET['pg'] > 1 ? (integer)$_GET['pg'] : 1),
				'length'	=> $this->_Parent->Configuration->get('pagination_maximum_rows', 'symphony')
			);
			
			$this->_rules = $this->_driver->getRules(
				$this->_table_column,
				$this->_table_direction,
				$this->_pagination->page,
				$this->_pagination->length
			);
			
			// Calculate pagination:
			$this->_pagination->start = max(1, (($page - 1) * 17));
			$this->_pagination->end = (
				$this->_pagination->start == 1
				? $this->_pagination->length
				: $start + count($this->_rules)
			);
			$this->_pagination->total = $this->_driver->countRules();
			$this->_pagination->pages = ceil(
				$this->_pagination->total / $this->_pagination->length
			);
		}
		
		public function generateLink($values) {
			$values = array_merge(array(
				'pg'	=> $this->_pagination->page,
				'sort'	=> $this->_table_column,
				'order'	=> $this->_table_direction
			), $values);
			
			$count = 0;
			$link = $this->_Parent->getCurrentPageURL();
			
			foreach ($values as $key => $value) {
				if ($count++ == 0) {
					$link .= '?';
				} else {
					$link .= '&amp;';
				}
				
				$link .= "{$key}={$value}";
			}
			
			return $link;
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $rule_id) {
							$this->_Parent->Database->query("
								DELETE FROM
									`tbl_redirectionmanager_rules`
								WHERE
									`id` = {$rule_id}
							");
						}
						
						redirect($this->_uri . '/rules/');
						break;
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle('Symphony &ndash; Redirection Manager &ndash; Rules');
			$this->appendSubheading('Rules', Widget::Anchor(
				'Create New', $this->_uri . '/rules/new/',
				'Create a new rule', 'create button'
			));
			
			$tableHead = array();
			$tableBody = array();
			
			// Columns, with sorting:
			foreach ($this->_table_columns as $column => $values) {
				if ($values[1]) {
					if ($column == $this->_table_column) {
						if ($this->_table_direction == 'desc') {
							$direction = 'asc';
							$label = 'ascending';
						} else {
							$direction = 'desc';
							$label = 'descending';
						}
					} else {
						$direction = 'asc';
						$label = 'ascending';
					}
					
					$link = $this->generateLink(array(
						'sort'	=> $column,
						'order'	=> $direction
					));
					
					$anchor = Widget::Anchor($values[0], $link, "Sort by {$label} " . strtolower($values[0]));
					
					if ($column == $this->_table_column) {
						$anchor->setAttribute('class', 'active');
					}
					
					$tableHead[] = array($anchor, 'col');
					
				} else {
					$tableHead[] = array($values[0], 'col');
				}
			}
			
			if (!is_array($this->_rules) or empty($this->_rules)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
				
			} else {
				foreach ($this->_rules as $rule) {
					$rule = (object)$rule;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$this->_driver->truncateValue($rule->name),
							$this->_uri . "/rules/edit/{$rule->id}/"
						)
					);
					$col_name->appendChild(Widget::Input("items[{$rule->id}]", null, 'checkbox'));
					
					$col_type = Widget::TableData($rule->type);
					
					
					if (!empty($rule->source)) {
						$col_source = Widget::TableData($rule->source);	
					} 
					
					if (!empty($rule->target)) {
						$col_target = Widget::TableData($rule->target);	
					} 
					
					if (!empty($rule->expression)) {
						$col_expression = Widget::TableData($rule->expression);
						
					} else {
						$col_expression = Widget::TableData('None', 'inactive');
					}
					
					$col_method = Widget::TableData(ucwords($rule->method));
										
					$tableBody[] = Widget::TableRow(array(
						$col_name, $col_type, $col_source, $col_target
					));
				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody)
			);
			$table->setAttribute('class', 'selectable');
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, 'With Selected...'),
				array('delete', false, 'Delete')
			);
			
			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));
			
			$this->Form->appendChild($actions);
			
			// Pagination:
			if ($this->_pagination->pages > 1) {
				$ul = new XMLElement('ul');
				$ul->setAttribute('class', 'page');
				
				## First
				$li = new XMLElement('li');
				
				if ($this->_pagination->page > 1) {
					$li->appendChild(
						Widget::Anchor('First', $this->generateLink(array(
							'pg'	=> 1
						)))
					);
					
				} else {
					$li->setValue('First');
				}
				
				$ul->appendChild($li);
				
				## Previous
				$li = new XMLElement('li');
				
				if ($this->_pagination->page > 1) {
					$li->appendChild(
						Widget::Anchor('&larr; Previous', $this->generateLink(array(
							'pg'	=> $this->_pagination->page - 1
						)))
					);
					
				} else {
					$li->setValue('&larr; Previous');
				}
				
				$ul->appendChild($li);

				## Summary
				$li = new XMLElement('li', 'Page ' . $this->_pagination->page . ' of ' . max($this->_pagination->page, $this->_pagination->pages));
				
				$li->setAttribute('title', 'Viewing ' . $this->_pagination->start . ' - ' . $this->_pagination->end . ' of ' . $this->_pagination->total . ' entries');
				
				$ul->appendChild($li);

				## Next
				$li = new XMLElement('li');
				
				if ($this->_pagination->page < $this->_pagination->pages) {
					$li->appendChild(
						Widget::Anchor('Next &rarr;', $this->generateLink(array(
							'pg'	=> $this->_pagination->page + 1
						)))
					);
					
				} else {
					$li->setValue('Next &rarr;');
				}
				
				$ul->appendChild($li);

				## Last
				$li = new XMLElement('li');
				
				if ($this->_pagination->page < $this->_pagination->pages) {
					$li->appendChild(
						Widget::Anchor('Last', $this->generateLink(array(
							'pg'	=> $this->_pagination->pages
						)))
					);
					
				} else {
					$li->setValue('Last');
				}
				
				$ul->appendChild($li);
				$this->Form->appendChild($ul);	
			}
		}
	}
	
?>