<?php

    class Products_Page extends Page {
    
        public function getInputs() {
            return array(
                        "actionAdd" => array("name" => "post", "price" => "post", "available" => "post", "event" => "post"),
                        "actionEdit" => array("id" => "post", "name" => "post", "price" => "post", "available" => "post", "event" => "post")
                        );
        }
    
        public function actionIndex() {
        
            $this->parent->auth->requireLogin();
            
            $this->parent->template->setSubtitle("Products");
            $this->parent->template->outputHeader();
            
            $res = $this->parent->db->query("SELECT * FROM `event`");
            $data["events"][] = array("event_id" => "", "event_name" => "None");
            while ($row = $res->fetch_assoc()) $data["events"][] = $row;
            
            $this->parent->template->outputTemplate("products", $data);
            $this->parent->template->outputFooter();
            
        }
        
        public function actionAdd() {
        
            $this->parent->auth->requireLogin();
            
            $this->inputs["available"] = str_replace(array("true", "false"), array(1, 0), $this->inputs["available"]);
            $this->inputs["price"] = preg_replace ('/((?![\d\.]).)*/', '', $this->inputs["price"]);
            
            //Error checking
            if (strlen($this->inputs["name"]) < 1 || strlen($this->inputs["name"]) > 100) $this->errorJSON("Invalid Product name - max of 100 characters");
            if (preg_match('/^\d{1,10}(\.\d\d?)?$/', $this->inputs["price"]) == 0) $this->errorJSON("Invalid price format");
            if ($this->inputs["available"] != 1 && $this->inputs["available"] != 0) $this->errorJSON("Invalid value for availability");
            
            //Check product name isn't in use
            $row = $this->parent->db->query("SELECT COUNT(*) FROM `product` WHERE LOWER(product_name) = '%s'", strtolower($this->inputs["name"]))->fetch_array();
            if ($row[0] != 0) $this->errorJSON("Product name already in use");
            
            //If event is supplied, check it exists
            if (is_numeric($this->inputs["event"]) && strlen($this->inputs["event"]) > 0) {
                $row = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $this->inputs["event"])->fetch_assoc();
                if (!$row) $this->errorJSON("Invalid event ID");
            }
            
            $this->parent->db->query("INSERT INTO `product` (product_name, price, available, event_id) VALUES ('%s', '%s', '%s', '%s')", $this->inputs["name"], $this->inputs["price"], $this->inputs["available"], $this->inputs["event"]);
            
        }
        
        public function actionGet() {
        
            $this->parent->auth->requireLogin();
            
            $res = $this->parent->db->query("SELECT * FROM `product`");
            $arr = array();
            while ($row = $res->fetch_array(MYSQLI_NUM)) {
            
                //If event
                $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $row[count($row) -1])->fetch_assoc();
                if ($event) $row[count($row) -1] = $event["event_name"];
                else $row[count($row) -1] = "None";
                
                //Tickets sold
                $row2 = $this->parent->db->query("SELECT COUNT(*) FROM `purchase` WHERE purchase.refunded = 0 AND purchase.product_id = '%s'", $row[0])->fetch_array();
                $row[] = $row2[0];
                
                $arr[] = $row;
            }
            echo json_encode($arr);
        
        }
        
        public function actionEdit() {
        
            $this->parent->auth->requireLogin();
            
            $this->inputs["available"] = str_replace(array("true", "false"), array(1, 0), $this->inputs["available"]);
            $this->inputs["price"] = preg_replace ('/((?![\d\.]).)*/', '', $this->inputs["price"]);
            
            //Error checking
            if (strlen($this->inputs["name"]) < 1 || strlen($this->inputs["name"]) > 100) $this->errorJSON("Invalid Product name - max of 100 characters");
            if (preg_match('/^\d{1,10}(\.\d\d?)?$/', $this->inputs["price"]) == 0) $this->errorJSON("Invalid price format");
            if ($this->inputs["available"] != 1 && $this->inputs["available"] != 0) $this->errorJSON("Invalid value for availability");
            
            //Check product id exists
            $row = $this->parent->db->query("SELECT * FROM `product` WHERE product_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if ($row == null) $this->errorJSON("Invalid Product Id");
            
            //Check product name isn't in use
            if (strtolower($this->inputs["name"]) != strtolower($row["product_name"])) {
                $row2 = $this->parent->db->query("SELECT COUNT(*) FROM `product` WHERE LOWER(product_name) = '%s'", strtolower($this->inputs["name"]))->fetch_array();
                if ($row2[0] != 0) $this->errorJSON("Product name already in use");
            }
            
            //If event is supplied, check it exists
            if (strlen($this->inputs["event"]) > 0 && $this->inputs["event"] != "None") {
                $row = $this->parent->db->query("SELECT * FROM `event` WHERE LOWER(event_name) = LOWER('%s')", $this->inputs["event"])->fetch_assoc();
                if (!$row) $this->errorJSON("Invalid event name");
                $this->inputs["event"] = $row["event_id"];
            } else {
                $this->inputs["event"] = "";
            }
            
            //If setting available to true and event isn't null check ticket availability
            if ($this->inputs["available"] == 1 && $this->inputs["event"] != "") {
                $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $this->inputs["event"])->fetch_assoc();
                $total = $this->parent->db->query("SELECT COUNT(*) FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND purchase.refunded = 0 AND product.event_id = '%s'", $this->inputs["event"])->fetch_array();
                if ($event["ticket_limit"] <= $total[0]) $this->errorJSON("Cannot make product available - tickets are sold out for the event assigned to this product");
            }
            
            //Insert it
            $this->parent->db->query("UPDATE `product` SET product_name = '%s', price = '%s', available = '%s', event_id = '%s' WHERE product_id = '%s'", $this->inputs["name"], $this->inputs["price"], $this->inputs["available"], $this->inputs["event"], $this->inputs["id"]);

            //Check availability
            $this->parent->validateProductAvailability();
        }
        
    }

?>