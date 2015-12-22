<?php

	//Database class
	class Db {
		
		private $config, $db;
		
		public function __construct($parent) {
			$this->config = $parent->config;
			$this->db = new mysqli($this->config->database["host"], $this->config->database["user"], $this->config->database["pass"], $this->config->database["db"]);
			if (mysqli_connect_errno()) die("Unable to connect to SQL Database: " . mysqli_connect_error());
			$this->createTables();
		}
        
        /**
         * Selects a new database
         */
		public function select_db($db) {
            $db->select_db($db) or die("Unable to select new datbase: " . mysqli_error());
        }
        
		/**
		 * Base MySQL query function. Cleans all parameters to prevent injection
		 */
		public function query() {
			$args = func_get_args();
			$sql = array_shift($args);
			foreach ($args as $key => $value) $args[$key] = $this->clean($value);
			$res = $this->db->query((count($args) > 0?vsprintf($sql, $args):$sql));
            if (!$res) die("MySQLi Error: " . mysqli_error($this->db));
            else return $res;
		}
        
        /**
         * Returns database link
         */
        public function getLink() {
            return $this->db;
        }
        
		/**
		 * Stops MySQL injection
		 */
		public function clean($string) {
			return $this->db->real_escape_string(trim($string));
		}
		
		/**
		 * Creates default tables if they don't exist
		 */
		private function createTables() {
            
            $query = 
                "CREATE TABLE IF NOT EXISTS `event` (
                  `event_id` int(11) NOT NULL AUTO_INCREMENT,
                  `event_name` varchar(50) NOT NULL,
                  `ticket_limit` int(11) NOT NULL,
                  PRIMARY KEY (`event_id`),
                  UNIQUE KEY `event_name` (`event_name`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;";
            $this->query($query);

            $query = 
                "CREATE TABLE IF NOT EXISTS `product` (
                  `product_id` int(11) NOT NULL AUTO_INCREMENT,
                  `product_name` varchar(100) NOT NULL,
                  `price` decimal(10,2) NOT NULL,
                  `available` tinyint(1) NOT NULL,
                  `event_id` int(50) DEFAULT NULL,
                  PRIMARY KEY (`product_id`),
                  UNIQUE KEY `product_name` (`product_name`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;";
            $this->query($query);

            $query = 
                "CREATE TABLE IF NOT EXISTS `purchase` (
                  `purchase_id` int(11) NOT NULL AUTO_INCREMENT,
                  `receipt_id` int(11) NOT NULL,
                  `product_id` int(11) NOT NULL,
                  `price` decimal(10,2) NOT NULL,
                  `refunded` tinyint(1) NOT NULL,
                  `refund_id` int(1) DEFAULT NULL,
                  PRIMARY KEY (`purchase_id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12 ;";
            $this->query($query);

            $query = 
                "CREATE TABLE IF NOT EXISTS `receipt` (
                  `receipt_id` int(11) NOT NULL AUTO_INCREMENT,
                  `date` date NOT NULL,
                  `name` varchar(100) NOT NULL,
                  `email` varchar(100) NOT NULL,
                  `student_id` varchar(7) NOT NULL,
                  `customer_forum_name` varchar(100) NOT NULL,
                  `comments` text NOT NULL,
                  `issuer_forum_name` varchar(100) NOT NULL,
                  `refunded` tinyint(1) NOT NULL,
                  PRIMARY KEY (`receipt_id`)
                ) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=6 ;";
            $this->query($query);

            $query = 
                "CREATE TABLE IF NOT EXISTS `refund` (
                  `refund_id` int(11) NOT NULL,
                  `receipt_id` int(11) NOT NULL,
                  `issuer_forum_name` varchar(100) NOT NULL,
                  `comments` text NOT NULL
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
                
            $this->query($query);
              
		}
		
	}
	
?>