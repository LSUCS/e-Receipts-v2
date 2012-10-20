<?php

	class Config {
		
		public $database;
		public $auth;
        public $page;
        public $email;
        public $api;
		
		function __construct() {
        
            require_once("../passwords.php");
			
			/**
			 * Database
			 */
			$this->database["host"] = "localhost";
			$this->database["user"] = "receipts";
			$this->database["pass"] = $database;
			$this->database["db"]   = "dev-receipts2";
            
			/**
			 *Auth
			 */
            $this->auth["xenforoDir"] = "/home/soc_lsucs/lsucs.org.uk/htdocs";
            $this->auth["xenforo_committee_group_id"] = 4;
            
            /**
             * Page Defaults
			 */
            $this->page["default_page"]  = "issue";
            $this->page["default_title"] = "Issue";
            
            /**
             * Email Settings
             */
			$this->email["user"] = "receipts@lsucs.org.uk";
			$this->email["pass"] = $email;
			$this->email["host"] = "ssl://smtp.gmail.com";
			$this->email["port"] = "465";
            $this->email["cc"]   = "committee@lsucs.org.uk";
            
            /**
             * API Settings
             */
            $this->api["api_key"] = $api;
            $this->api["lan_api_submit_url"] = "http://lan.lsucs.org.uk/index.php?page=api&action=issuetickets";
            $this->api["lan_api_refund_url"] = "http://lan.lsucs.org.uk/index.php?page=api&action=deletetickets";
            
		}
		
	}

?>