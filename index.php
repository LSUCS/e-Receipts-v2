<?php

    ini_set('display_errors','On');
    error_reporting(E_ALL);
    setlocale(LC_MONETARY, 'en_GB');

	//Includes
	include("config.php");
	include("db.php");
    include("auth.php");
    include("template.php");
	include("pages/page.php");
    include("email.php");

	$main = new Main();
	
	class Main {
		
		//Setup some variables
		public $config;
		public $db;
        public $settings;
        public $auth;
        public $template;
		public $pages;
		public $page;
		
		function __construct() {
			
            //Load base classes
			$this->config   = new Config();
			$this->db       = new Db($this);
            $this->auth     = new Auth($this);
            
            //Load template manager
            $this->template = new Template($this);
            
            //Add nav elements
            $this->template->addNavElement("index.php?page=issue", "Issue Receipt", "issue");
            $this->template->addNavElement("index.php?page=search", "Search Receipts ", "search");
            if ($this->auth->isAdmin()) $this->template->addNavElement("index.php?page=products", "Products", "products");
            if ($this->auth->isAdmin()) $this->template->addNavElement("index.php?page=events", "Events", "events");
        
            //Set valid pages
            $this->pages = array("issue", "search", "products", "events", "refund", "account", "api");
        
            //Parse page - if invalid, load 'not found' template
            if (!isset($_GET["page"]) || $_GET["page"] == "") {
                $this->page = $this->config->page["default_page"];
            } else if (in_array(strtolower($_GET["page"]), $this->pages)) {
                $this->page = strtolower($_GET["page"]);
            } else {
                $this->template->setSubtitle("Page not found");
                $this->page = "Page not found";
                $this->template->outputHeader();
                $this->template->outputFooter();
                return;
            }
            
            //Include requested page
            include("pages/" . $this->page . ".php");
            $class = $this->page . "_Page";
            $child = new $class($this);
        
			//See if there is a specified action to run or if we are running default
			$method = 'actionIndex';
			if (isset($_GET["action"]) && method_exists($child, "action" . ucwords(strtolower($_GET["action"])))) {
				$method = 'action' . ucwords(strtolower($_GET["action"]));
			}
            
            //Validate inputs against running method and store in child
            $inputarr = $child->getInputs();
            foreach ($inputarr as $page => $inputs) {
                if ($method == $page) {
                    foreach ($inputs as $input => $type) {
                        if ($type == "post" && isset($_POST[$input])) {
                            $child->inputs[$input] = $_POST[$input];
                        } else if ($type == "get" && isset($_GET[$input])) {
                            $child->inputs[$input] = $_GET[$input];
                        } else {
                            $child->inputs[$input] = "";
                        }
                    }
                }
            }
			
			//Run child page action
			call_user_func(array($child, $method));
			
		}
        
        /**
         * Loops through all events checking if they are sold out
         */
        function validateProductAvailability() {
            
            $res = $this->db->query("SELECT * FROM `event`");
            while ($event = $res->fetch_assoc()) {
                $total = $this->db->query("SELECT COUNT(*) FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND purchase.refunded = 0 AND product.event_id = '%s'", $event["event_id"])->fetch_array();
                //If sold out, make unavailable
                if ($total[0] >= $event["ticket_limit"]) {
                    $this->db->query("UPDATE `product` SET available = 0 WHERE event_id = '%s'", $event["event_id"]);
                }
            }
            
        }
		
	}
	
?>