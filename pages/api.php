<?php

    class Api_Page extends Page {
    
        public function getInputs() {
            return array(
                        "actionLanavailability" => array("lan" => "post"),
                        "actionIssuelanreceipt" => array("lan" => "post", "member_amount" => "post", "nonmember_amount" => "post", "name" => "post", "email" => "post", "customer_forum_name" => "post", "student_id" => "post")
                        );
        }
    
        public function actionIndex() {
            $this->authenticate();
            echo $this->errorJSON("Invalid API Method");
        }
        
        public function actionLanavailability() {
            $this->authenticate();
            $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_name = 'LAN%s'", $this->inputs["lan"])->fetch_assoc();
            if ($event) {
                $count = $this->parent->db->query("SELECT COUNT(*) FROM `purchase` WHERE product_id IN (SELECT product_id FROM `product` WHERE event_id = '%s') AND refunded = 0", $event["event_id"])->fetch_array();
                echo json_encode(array("availability" => $event["ticket_limit"] - $count[0]));
            } else {
                $this->errorJSON("LAN event not found");
            }
        }
        
        public function actionIssuelanreceipt() {
        
            $this->authenticate();
            
            /********************/
            // VALIDATE
            /********************/
            $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_name = 'LAN%s'", $this->inputs["lan"])->fetch_assoc();
            if (!$event) $this->errorJSON("LAN event not found");
            file_put_contents("derp.txt", "hi");
            if ($this->inputs["member_amount"] != "" && !is_numeric($this->inputs["member_amount"])) $this->errorJSON("Invalid member amount");
            if ($this->inputs["nonmember_amount"] != "" && !is_numeric($this->inputs["nonmember_amount"])) $this->errorJSON("Invalid nonmember amount");
            if ($this->inputs["name"] == "") $this->errorJSON("Invalid name");
            if ($this->inputs["email"] == "") $this->errorJSON("Invalid email");
            if ($this->inputs["customer_forum_name"] == "") $this->errorJSON("Invalid customer forum name");
            
            //Check availability
            $count = $this->parent->db->query("SELECT COUNT(*) FROM `purchase` WHERE product_id IN (SELECT product_id FROM `product` WHERE event_id = '%s') AND refunded = 0", $event["event_id"])->fetch_array();
            if ($this->inputs["member_amount"] + $this->inputs["nonmember_amount"] > $event["ticket_limit"] - $count[0]) $this->errorJSON("Not enough tickets available");
            
            //Get products
            $memberProduct = $this->parent->db->query("SELECT * FROM `product` WHERE LOWER(product_name) = LOWER('LAN%s - Member')", $this->inputs["lan"])->fetch_assoc();
            $nonmemberProduct = $this->parent->db->query("SELECT * FROM `product` WHERE LOWER(product_name) = LOWER('LAN%s - Non-Member')", $this->inputs["lan"])->fetch_assoc();
            
            
            /********************/
            // ADD TO DATABASE
            /********************/
            //Issue receipt
            $this->parent->db->query("INSERT INTO `receipt` (date, name, email, student_id, customer_forum_name, comments, issuer_forum_name) VALUES (CURDATE(), '%s', '%s', '%s', '%s', 'Issued by LAN Website', 'API')", $this->inputs["name"], $this->inputs["email"], $this->inputs["student_id"], $this->inputs["customer_forum_name"]); 
            $receiptID = $this->parent->db->getLink()->insert_id;
            
            //Add products
            $purchases = array();
            for ($i = 0; $i < $this->inputs["member_amount"] + $this->inputs["nonmember_amount"]; $i++) {
            
                //Member or non-member
                if ($i < $this->inputs["member_amount"]) {
                    $productID = $memberProduct["product_id"];
                    $price = $memberProduct["price"];
                } else {
                    $productID = $nonmemberProduct["product_id"];
                    $price = $nonmemberProduct["price"];
                }
                
                //Insert and get insert ID
                $this->parent->db->query("INSERT INTO `purchase` (receipt_id, product_id, price) VALUES ('%s', '%s', '%s')", $receiptID, $productID, $price);
                $purchaseID = $this->parent->db->getLink()->insert_id;
                
                $purchases[] = array("type" => ($i < $this->inputs["member_amount"]?"member":"non_member"), "purchase_id" => $purchaseID);
                
            }
            
            /********************/
            // ADD TICKETS
            /********************/
            //Set up field values
            $fields = array("api_key" => $this->parent->config->api["api_key"],
                            "purchases" => json_encode($purchases),
                            "lan" => $this->inputs["lan"],
                            "name" => $this->inputs["name"],
                            "email" => $this->inputs["email"],
                            "forum_name" => $this->inputs["customer_forum_name"]);   
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
            foreach ($purchases as $key => $purchase) {
                if ($purchase["type"] == "member") $product = $memberProduct;
                else $product = $nonmemberProduct;
                $prodTable .= "<tr style='background-color: #e8e8e8; color: black;'><td style='padding: 2px;' width='200px'>" . $product["product_name"] . "</td><td style='padding: 2px;' width='100px'>&pound;" . $product["price"] . "</td></tr>";
                $total += $product["price"];
            }
            $prodTable .="</table>";
            $total = "&pound;" . number_format($total, 2);
            
            //Format template
            $email->replaceKey("%CUSTNAME%", $this->inputs["name"]);
            $email->replaceKey("%CUSTEMAIL%", $this->inputs["email"]);
            $email->replaceKey("%CUSTID%", ($this->inputs["student_id"] == ""?"N/A":$this->inputs["student_id"]));
            $email->replaceKey("%ORDERDATE%", date('l jS \of F Y h:i:s A'));
            $email->replaceKey("%FORUMNAME%", $this->inputs["customer_forum_name"]);
            $email->replaceKey("%PRODTABLE%", $prodTable);
            $email->replaceKey("%ORDERTOTAL%", $total);
            $email->replaceKey("%ORDERCOMMENTS%", "None");
            $email->replaceKey("%ISSUER%", "LAN Website");
            $email->replaceKey("%LANCLAIM%", "");
            
            //Send
            if (!$email->send()) {
                $this->rollback($receiptID);
                $this->errorJSON("Unable to send e-receipt");
            }
            
            echo json_encode(array("success" => true));
            
        }
        
        private function authenticate() {
            $this->parent->auth->requireNotLoggedIn();
            if (!isset($_POST["api_key"]) || sha1($_POST["api_key"]) != sha1($this->parent->config->api["api_key"])) {
                $this->errorJSON("Invalid API Key");
            }
        }
        
        private function rollback($receiptID) {
            $this->parent->db->query("DELETE FROM `receipt` WHERE receipt_id = '%s'", $receiptID);
            $this->parent->db->query("DELETE FROM `purchase` WHERE receipt_id = '%s'", $receiptID);
        }
    
    }

?>