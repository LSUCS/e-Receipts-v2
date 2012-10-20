<?php

    class Issue_Page extends Page {
    
        public function getInputs() {
            return array(
                "actionSearch" => array("type" => "get", "term" => "get"),
                "actionAutofill" => array("type" => "post", "value" => "post"),
                "actionSubmit" => array("email" => "post", "name" => "post", "student_id" => "post", "forum" => "post", "products" => "post", "comments" => "post"),
                );
        }
	
		public function actionIndex() {
        
            $this->parent->auth->requireLogin();
		
			$this->parent->template->setSubTitle("Issue Receipt");
			$this->parent->template->outputHeader();
            $this->parent->template->outputTemplate("issue");
			$this->parent->template->outputFooter();
		
		}
        
        public function actionSearch() {
        
            $this->parent->auth->requireLogin();
            $results = array();
            
            switch ($this->inputs["type"]) {
                case "name":
                    $res = $this->parent->db->query("SELECT DISTINCT(name) FROM `receipt` WHERE name LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["name"];
                    break;
                case "email":
                    $res = $this->parent->db->query("SELECT DISTINCT(email) FROM `receipt` WHERE email LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["email"];
                    break; 
                case "studentid":
                    $res = $this->parent->db->query("SELECT DISTINCT(student_id) FROM `receipt` WHERE student_id LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["student_id"];
                    break; 
                case "forum":
                    $res = $this->parent->db->query("SELECT DISTINCT(customer_forum_name) FROM `receipt` WHERE customer_forum_name LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["customer_forum_name"];
                    break;
                case "issuer_forum_name":
                    $res = $this->parent->db->query("SELECT DISTINCT(issuer_forum_name) FROM `receipt` WHERE issuer_forum_name LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["issuer_forum_name"];
                    break;
                case "product":
                    $res = $this->parent->db->query("SELECT DISTINCT(product_name) FROM `product` WHERE product_name LIKE '%%%s%%'", $this->inputs["term"]);
                    while ($row = $res->fetch_assoc()) $results[] = $row["product_name"];
                    break;
            }
            
            echo json_encode($results);
        }
        
        public function actionAutofill() {
            $this->parent->auth->requireLogin();        
            if (!in_array($this->inputs["type"], array("name", "email", "student_id", "customer_forum_name", "issuer_forum_name"))) return;
            echo json_encode($this->parent->db->query("SELECT * FROM `receipt` WHERE %s = '%s' ORDER BY receipt_id DESC", $this->inputs["type"], $this->inputs["value"])->fetch_assoc());
        }
        
        public function actionProducts() {
            $this->parent->auth->requireLogin(); 
            $res = $this->parent->db->query("SELECT * FROM `product` WHERE available=1");
            $data = array();
            while ($row = $res->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
        }
        
        public function actionSubmit() {
        
            $this->parent->auth->requireLogin();
            $userdata = $this->parent->auth->getUserData();
            $customerforumdata = $this->parent->auth->getUserByName($this->inputs["forum"]);
        
            /********************/
            // VALIDATION
            /********************/
            //Error checking
            if (strlen($this->inputs["name"]) < 1 || strlen($this->inputs["name"]) > 100) $this->errorJSON("Invalid name");
            if (strlen($this->inputs["student_id"]) > 0 && preg_match('/^[a-z]\d{6}$/i', $this->inputs["student_id"]) == 0) $this->errorJSON("Invalid student ID");
            if (preg_match('/^(?:(?:(?:[^@,"\[\]\x5c\x00-\x20\x7f-\xff\.]|\x5c(?=[@,"\[\]\x5c\x00-\x20\x7f-\xff]))(?:[^@,"\[\]\x5c\x00-\x20\x7f-\xff\.]|(?<=\x5c)[@,"\[\]\x5c\x00-\x20\x7f-\xff]|\x5c(?=[@,"\[\]\x5c\x00-\x20\x7f-\xff])|\.(?=[^\.])){1,62}(?:[^@,"\[\]\x5c\x00-\x20\x7f-\xff\.]|(?<=\x5c)[@,"\[\]\x5c\x00-\x20\x7f-\xff])|[^@,"\[\]\x5c\x00-\x20\x7f-\xff\.]{1,2})|"(?:[^"]|(?<=\x5c)"){1,62}")@(?:(?!.{64})(?:[a-zA-Z0-9][a-zA-Z0-9-]{1,61}[a-zA-Z0-9]\.?|[a-zA-Z0-9]\.?)+\.(?:xn--[a-zA-Z0-9]+|[a-zA-Z]{2,6})|\[(?:[0-1]?\d?\d|2[0-4]\d|25[0-5])(?:\.(?:[0-1]?\d?\d|2[0-4]\d|25[0-5])){3}\])$/', $this->inputs["email"]) == 0) $this->errorJSON("Invalid email format");
            if (strlen($this->inputs["comments"]) > 200) $this->errorJSON("Comments may not be over 200 characters");
            if (strlen($this->inputs["forum"]) > 0 && $customerforumdata == null) $this->errorJSON("Invalid forum name");
            if (strtolower($this->inputs["email"]) == strtolower($userdata["email"]) || (isset($userdata["customFields"]["real_name"]) && strtolower($this->inputs["name"]) == strtolower($userdata["customFields"]["real_name"])) || (strlen($this->inputs["forum"]) > 0 && strtolower($userdata["username"]) == strtolower($customerforumdata["username"]))) $this->errorJSON("You cannot issue yourself a receipt");
            
            //Check products
            if (strlen($this->inputs["products"]) == 0) $this->errorJSON("You must add at least 1 product");
            $products = explode(",", $this->inputs["products"]);
            $events = array();
            foreach ($products as $key => $productID) {
            
                //Check if valid ID
                $product = $this->parent->db->query("SELECT * FROM `product` WHERE product_id = '%s' AND available = true", $productID)->fetch_assoc();
                if ($product == null) $this->errorJSON("Invalid product ID");
                
                //If part of an event, add to events array for checking
                if ($product["event_id"] > 0) $events[] = $product["event_id"];
                
                $products[$key] = $product;
            }
            
            //Check event availability
            $counts = array_count_values($events);
            foreach ($events as $eventID) {
                $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $eventID)->fetch_assoc();
                $total = $this->parent->db->query("SELECT COUNT(*) FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND purchase.refunded = 0 AND product.event_id = '%s'", $eventID)->fetch_array();
                //If there aren't enough available tickets for the amount of selected product, error
                if (($event["ticket_limit"] - $total[0]) < $counts[$eventID]) {
                    $this->errorJSON("Only " . ($event["ticket_limit"] - $total[0]) . " tickets available for " . $event["event_name"] . " - " . $counts[$eventID] . " requested");
                }
            }  

            /********************/
            // INSERT
            /********************/   
            //Add receipt to database
            $this->parent->db->query("INSERT INTO `receipt` (date, name, email, student_id, customer_forum_name, comments, issuer_forum_name) VALUES (CURDATE(), '%s', '%s', '%s', '%s', '%s', '%s')", $this->inputs["name"], $this->inputs["email"], $this->inputs["student_id"], $this->inputs["forum"], $this->inputs["comments"], $userdata["username"]);
            $receiptID = $this->parent->db->getLink()->insert_id;
            
            //Add purchased products to database
            $lanPurchases = array();
            foreach ($products as $product) {
                $this->parent->db->query("INSERT INTO `purchase` (receipt_id, product_id, price) VALUES ('%s', '%s', '%s')", $receiptID, $product["product_id"], $product["price"]);
                $purchaseID = $this->parent->db->getLink()->insert_id;
                
                //LAN ticket checking
                if ($product["event_id"] != "") {
                    //Check if event is a LAN
                    $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $product["event_id"])->fetch_assoc();
                    if ($event && preg_match("/^LAN(\d\d+?)$/", $event["event_name"], $matches) == 1) {
                        //Member ticket?
                        if (preg_match("/non\-?member/i", $product["product_name"])) $lanPurchases[] = array("type" => "non_member", "purchase_id" => $purchaseID);
                        else $lanPurchases[] = array("type" => "member", "purchase_id" => $purchaseID);
                    }
                }
                
            }
            //Validate availability
            $this->parent->validateProductAvailability();
            
            
            /********************/
            // SUBMIT TICKETS
            /********************/
            $claimKeys = array();
            if (count($lanPurchases) > 0) {
            
                //Set up field values
                $fields = array("api_key" => $this->parent->config->api["api_key"],
                                "purchases" => json_encode($lanPurchases),
                                "lan" => "35",
                                "name" => $this->inputs["name"],
                                "email" => $this->inputs["email"],
                                "forum_name" => $this->inputs["forum"]);   
                $fields_string = "";
                foreach($fields as $key=>$value) $fields_string .= $key.'='.$value.'&';
                rtrim($fields_string, '&');
                
                //Set up cURL and request                       
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $this->parent->config->api["lan_api_submit_url"]);
                curl_setopt($ch,CURLOPT_POST, count($fields));
                curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
                $result = json_decode(curl_exec($ch), true);
                
                //If error
                if (isset($result["error"])) {
                    $this->rollback($receiptID);
                    $this->errorJSON($result["error"]);
                } else if (!isset($result["success"])) {
                    $this->rollback($receiptID);
                    $this->errorJSON($result);
                }
                
                //Claim keys?
                if (isset($result["keys"])) $claimKeys = $result["keys"];
                
            }
            
            
            /********************/
            // SEND EMAIL
            /********************/
            //Set up email wrapper
            $email = new EmailWrapper($this->parent);
            $email->loadTemplate('receipt');
            $email->setTo($this->inputs["email"]);
            $email->setSubject("Receipt for your payment to LSU Computer Society");
            
            //Format product table for email
            $prodTable = "<table style='border-color: black;'><tr style='background-color: #999;'><td style='padding: 5px' width='200px'>Product</td><td style='padding: 5px' width='100px'>Price</td></tr>";
            $total = 0;
            foreach ($products as $key => $product) {
                $prodTable .= "<tr style='background-color: #e8e8e8; color: black;'><td style='padding: 2px;' width='200px'>" . $product["product_name"] . "</td><td style='padding: 2px;' width='100px'>&pound;" . $product["price"] . "</td></tr>";
                $total += $product["price"];
            }
            $prodTable .="</table>";
            $total = "&pound;" . number_format($total, 2);
            
            //Format LAN claim codes
            $claimText = "";
            if (count($claimKeys) > 0) {
                $claimText .= '<p>You have purchased LAN tickets, however a valid forum account was not provided to allocate the tickets to. <br />
                                Please use the links below or use the claim codes on the <a href="http://lan.lsucs.org.uk/index.php?page=account">LAN Website</a> to allocate your tickets to a forum account. <br />
                                If you are a member and have not got a members account on the forums you will need to get your account upgraded by following the details <a href="http://lsucs.org.uk/threads/what-do-you-post-here.5278/">here</a>.</p>';
                foreach ($claimKeys as $code) {
                    $claimText .= '<p stype="margin-left: 30px;"><a href="http://lan.lsucs.org.uk/index.php?page=account&code=' . $code["key"] . '">Claim ' . str_replace(array("non_member", "member"), array("Non-Member", "Member"), $code["type"]) . ' Ticket</a> - ' . $code["key"] . '</p>';
                }
            }
            
            //Format template
            $email->replaceKey("%CUSTNAME%", $this->inputs["name"]);
            $email->replaceKey("%CUSTEMAIL%", $this->inputs["email"]);
            $email->replaceKey("%CUSTID%", ($this->inputs["student_id"] == null?"N/A":$this->inputs["student_id"]));
            $email->replaceKey("%ORDERDATE%", date('l jS \of F Y h:i:s A'));
            $email->replaceKey("%FORUMNAME%", $this->inputs["forum"]);
            $email->replaceKey("%PRODTABLE%", $prodTable);
            $email->replaceKey("%ORDERTOTAL%", $total);
            $email->replaceKey("%ORDERCOMMENTS%", ($this->inputs["comments"] == ""?"None":$this->inputs["comments"]));
            $email->replaceKey("%ISSUER%", $userdata["username"]);
            $email->replaceKey("%LANCLAIM%", $claimText);
            
            //Send
            if (!$email->send()) {
                $this->rollback($receiptID);
                $this->errorJSON("Unable to send e-receipt - is the email address correct?");
            }
            
        }
        
        private function rollback($receiptID) {
            $this->parent->db->query("DELETE FROM `receipt` WHERE receipt_id = '%s'", $receiptID);
            $this->parent->db->query("DELETE FROM `purchase` WHERE receipt_id = '%s'", $receiptID);
        }
    
    }

?>