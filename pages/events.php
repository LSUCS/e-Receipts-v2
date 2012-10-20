<?php

    class Events_Page extends Page {
    
        public function getInputs() {
            return array(
                        "actionAdd" => array("name" => "post", "ticket_limit" => "post"),
                        "actionEdit" => array("id" => "post", "name" => "post", "ticket_limit" => "post")
                        );
        }
    
        public function actionIndex() {
        
            $this->parent->auth->requireLogin();
            
            $this->parent->template->setSubtitle("Events");
            $this->parent->template->outputHeader();
            $this->parent->template->outputTemplate("events");
            $this->parent->template->outputFooter();
        
        }
        
        public function actionGet() {
        
            $this->parent->auth->requireLogin();
            
            $res = $this->parent->db->query("SELECT * FROM `event`");
            $arr = array();
            while ($row = $res->fetch_array()) {
                $row2 = $this->parent->db->query("SELECT COUNT(*) FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND purchase.refunded = 0 AND product.event_id = '%s'", $row["event_id"])->fetch_array();
                $row[] = $row2[0];
                $arr[] = $row;
            }
            
            echo json_encode($arr);
        
        }
        
        public function actionAdd() {
        
            $this->parent->auth->requireLogin();
            
            //Error checking
            if (strlen($this->inputs["name"]) < 1 || strlen($this->inputs["name"]) > 100) $this->errorJSON("Invalid Event name - max of 50 characters");
            if (!is_numeric($this->inputs["ticket_limit"]) || $this->inputs["ticket_limit"] < 1) $this->errorJSON("Ticket limit must be greater than 0");
            
            //Check event name isn't in use
            $row = $this->parent->db->query("SELECT COUNT(*) FROM `event` WHERE LOWER(event_name) = '%s'", strtolower($this->inputs["name"]))->fetch_array();
            if ($row[0] != 0) $this->errorJSON("Event name already in use");
            
            
            $this->parent->db->query("INSERT INTO `event` (event_name, ticket_limit) VALUES ('%s', '%s')", $this->inputs["name"], $this->inputs["ticket_limit"]);
        
        }
        
        public function actionEdit() {
        
            $this->parent->auth->requireLogin();
            
            //Error checking
            if (strlen($this->inputs["name"]) < 1 || strlen($this->inputs["name"]) > 100) $this->errorJSON("Invalid Event name - max of 50 characters");
            if (!is_numeric($this->inputs["ticket_limit"]) || $this->inputs["ticket_limit"] < 1) $this->errorJSON("Ticket limit must be greater than 0");
            
            //Check product id exists
            $row = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if ($row == null) $this->errorJSON("Invalid Event Id");
            
            //Check product name isn't in use
            if (strtolower($this->inputs["name"]) != strtolower($row["event_name"])) {
                //Check event name isn't in use
                $row = $this->parent->db->query("SELECT COUNT(*) FROM `event` WHERE LOWER(event_name) = '%s'", strtolower($this->inputs["name"]))->fetch_array();
                if ($row[0] != 0) $this->errorJSON("Event name already in use");
            }
            
            //Insert it
            $this->parent->db->query("UPDATE `event` SET event_name = '%s', ticket_limit = '%s' WHERE event_id = '%s'", $this->inputs["name"], $this->inputs["ticket_limit"], $this->inputs["id"]);

            //Check availability
            $this->parent->validateProductAvailability();
        }
    
    }

?>