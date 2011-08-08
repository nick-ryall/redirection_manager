<?php
	
	class extension_redirection_manager extends Extension {
	/*-------------------------------------------------------------------------
		Extension definition
	-------------------------------------------------------------------------*/
	
		public function about() {
			return array(
				'name'			=> 'Redirecton Manager',
				'version'		=> '0.1',
				'release-date'	=> '2011-06-20',
				'author'		=> array(
					'name'			=> 'Nick Ryall',
					'website'		=> 'http://randb.com.au',
					'email'			=> 'nick@randb.com.au'
				),
				'description' => 'Manage and fix 404 errors and create new redirects.'
			);
		}
		
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_redirectionmanager_rules`");
			Symphony::Database()->query("DROP TABLE `tbl_redirectionmanager_logs`");
		}
		
		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_redirectionmanager_rules` (
					`id` int(11) NOT NULL auto_increment,
					`name` varchar(255) default NULL,
					`type` enum('301', '302', '307') default '301',
					`sortorder` int(11) NOT NULL,
					`source` varchar(255) default NULL,
					`source_compiled` varchar(255) DEFAULT NULL,
					`target` varchar(255) default NULL,
					`target_compiled` varchar(255) DEFAULT NULL,
					`method` enum('url', 'regexp') default 'url',
					PRIMARY KEY  (`id`),
					KEY `sortorder` (`sortorder`)
				)
			");
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_redirectionmanager_logs` (
					`id` int(11) NOT NULL auto_increment,
					`type` enum('301', '302', '307', '404') default '404',
					`request_time` int(11) default NULL,
					`request_uri` varchar(255) default NULL,
					`target_uri` varchar(255) default NULL,
					`request_method` enum('get', 'post') NOT NULL,
					`request_args` text default NULL,
					`remote_addr` varchar(255) default NULL,
					`hits` int(11) default NULL,
					`related_rule` int(11) default NULL,
					PRIMARY KEY  (`id`),
					KEY `request_time` (`request_time`)
				)
			");
			
			return true;
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page'		=> '/backend/',
					'delegate'	=> 'InitaliseAdminPageHead',
					'callback'	=> 'initializeAdmin'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'addFilterToEventEditor'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'addFilterToEventEditor'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventFinalSaveFilter',
					'callback'	=> 'eventFinalSaveFilter'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'FrontendPageResolved',
					'callback'	=> 'initializeRules'
				),
			);
		}
		
		public function fetchNavigation() {
			return array(
				array(
					'location'	=> 240,
					'name'		=> 'Redirection Manager',
					'children'	=> array(
						array(
							'name'		=> 'Rules',
							'link'		=> '/rules/'
						),
						array(
							'name'		=> 'Logs',
							'link'		=> '/logs/'
						)
					)
				)
			);
		}
		
		public function initializeRules($context) {
		
			//Get Page Type
			$page_type = $context['page_data']['type'][0];
			
			//Get the request URI and any the first related rule.
			$root_dir = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], "/")+1); 
			$request_uri = '/'.str_replace('//', '/', str_replace($root_dir, '', $_SERVER['REQUEST_URI']));
			$current_rule = $this->getRuleByURI($request_uri);
			
			if ($page_type== '404' || $current_rule['type'] != '') {
			
				if($current_rule['method'] == 'regexp') {
					$target_uri = preg_replace($current_rule['source_compiled'], $current_rule['target_compiled'], $request_uri);
					$target_uri = str_replace('//', '/', str_replace('\\/', '/', $target_uri));
				} else {
					$target_uri = $current_rule['target'];
				}
			
				
				//Check if there is a redirect rule for the giver URI
				if($current_rule['type'] == '301') {
					//Log the 301
					$this->updateLog('301', $target_uri, $request_uri, $current_rule['id']);
					
					// Permanent redirect
					header('HTTP/1.1 301 Moved Permanently');
					header("Location:".str_replace('//','/',$root_dir.$target_uri));
					exit();
				}
				if($current_rule['type'] == '302') {
					
					//Log the 302
					$this->updateLog('302', $target_uri, $request_uri, $current_rule['id']);
					
					// Redirect
					header("Location:".str_replace('//','/',$root_dir.$target_uri));
					exit();
				}
				if($current_rule['type'] == '307') {
				
					//Log the 307
					$this->updateLog('307', $target_uri, $request_uri, $current_rule['id']);
					
					// Temporary redirect
					header('HTTP/1.1 307 Moved Temporarily');
					header("Location:".str_replace('//','/',$root_dir.$target_uri));
					exit();
				} else {
					//No rule so just log the 404
					$this->updateLog('404', null, $request_uri, null);
					
				}
			}
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		public function truncateValue($value) {
			$max_length = $this->_Parent->Configuration->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);
			
			$value = General::sanitize($value);
			$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '...');
			
			return $value;
		}
		
	/*-------------------------------------------------------------------------
		Data:
	-------------------------------------------------------------------------*/
		
		public function checkLogs($request_uri, $type) {
			return Symphony::Database()->fetchRow(0, "
				SELECT
					l.id, l.hits, l.type
				FROM
					`tbl_redirectionmanager_logs` AS l
				WHERE
					l.request_uri = '{$request_uri}'
				AND l.type = '{$type}'
				LIMIT 1
			");	
		}
		public function updateLog($type, $target_uri, $request_uri, $related_rule_id) {
			$current_log = $this->checkLogs($request_uri, $type);
			//If entry exists for the given URI - update the counts + 1
			if(!empty($current_log)) {
			
				Symphony::Database()->update(array(
					'type'              => $type,
					'remote_addr'		=> $_SERVER['REMOTE_ADDR'],
					'request_time'		=> time(),
					'hits'				=> ($current_log['hits'] + 1),
					'request_uri'		=> $request_uri,
					'target_uri'		=> $target_uri,
					'request_method'	=> strtolower($_SERVER['REQUEST_METHOD']),
					'related_rule'	    => $related_rule_id,
					'request_args'		=> serialize(array(
						'get'	=> $_GET,
						'post'	=> $_POST
					))
				), 'tbl_redirectionmanager_logs', 'id ='.$current_log['id']);
				
			
			} else {
				//Otherwise store a new entry in the log
				Symphony::Database()->insert(array(
					'type'              => $type,
					'remote_addr'		=> $_SERVER['REMOTE_ADDR'],
					'request_time'		=> time(),
					'hits'              => 1,
					'request_uri'		=> $request_uri,
					'target_uri'		=> $target_uri,
					'request_method'	=> strtolower($_SERVER['REQUEST_METHOD']),
					'related_rule'	    => $related_rule_id,
					'request_args'		=> serialize(array(
						'get'	=> $_GET,
						'post'	=> $_POST
					))
				), 'tbl_redirectionmanager_logs');
				
			}
		}
		
		public function incrementLog($log_id) {
			$log_id = (integer)$log_id;
			
			return Symphony::Database()->fetchRow(0, "
				SELECT
					l.*
				FROM
					`tbl_redirectionmanager_logs` AS l
				WHERE
					l.id = '{$log_id}'
				LIMIT 1
			");
		}
		
		public function countLogs() {
			return (integer)Symphony::Database()->fetchVar('total', 0, "
				SELECT
					COUNT(l.id) AS `total`
				FROM
					`tbl_redirectionmanager_logs` AS l
			");
		}
		
		public function getLogs($column, $direction, $page, $length) {
			$start = ($page - 1) * $length;
			return Symphony::Database()->fetch("
				SELECT
					l.*
				FROM
					`tbl_redirectionmanager_logs` AS l
				ORDER BY
					l.{$column} {$direction},
					l.request_time DESC
				LIMIT {$start}, {$length}
			");
		}
		
		//				
		
		public function getLog($log_id) {
			$log_id = (integer)$log_id;
			
			return Symphony::Database()->fetchRow(0, "
				SELECT
					l.*
				FROM
					`tbl_redirectionmanager_logs` AS l
				WHERE
					l.id = '{$log_id}'
				LIMIT 1
			");
		}
		
		public function countRules() {
			return (integer)$this->_Parent->Database->fetchVar('total', 0, "
				SELECT
					COUNT(r.id) AS `total`
				FROM
					`tbl_redirectionmanager_rules` AS r
			");
		}
		
		public function getRules($column = 'name', $direction = 'asc', $page = 1, $length = 10000) {
			$start = ($page - 1) * $length;
			
			return Symphony::Database()->fetch("
				SELECT
					r.*
				FROM
					`tbl_redirectionmanager_rules` AS r
				ORDER BY
					r.{$column} {$direction},
					r.name ASC
				LIMIT {$start}, {$length}
			");
		}
		
		public function getRule($rule_id) {
			$rule_id = (integer)$rule_id;
			
			return Symphony::Database()->fetchRow(0, "
				SELECT
					r.*
				FROM
					`tbl_redirectionmanager_rules` AS r
				WHERE
					r.id = {$rule_id}
			");
		}
		
		public function getRuleByURI($uri) {
			
			// Find matching rules:
			$entries = array();
			$rows = Symphony::Database()->fetch("
				SELECT
					f.id,
					f.source,
					f.source_compiled
				FROM
					`sym_redirectionmanager_rules` AS f
			");
			
		
			foreach ($rows as $row) if (preg_match($row['source_compiled'], $uri)) {
				
				return Symphony::Database()->fetchRow(0, "
					SELECT
						r.*
					FROM
						`tbl_redirectionmanager_rules` AS r
					WHERE
						r.id = '{$row['id']}'
					LIMIT 1
				");
			}

		}
				
	}	