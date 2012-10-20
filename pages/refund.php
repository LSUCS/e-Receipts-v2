<?php

    class Refund_Page extends Page {
    
        public function getInputs() {
            return array(
                        "actionIndex" => array("id" => "get"),
                        "actionSubmit" => array("id" => "post", "purchases" => "post", "comments" => "post")
                        );
        }
        
        public function actionIndex() {
        
            $this->parent->auth->requireLogin();
            $this->parent->template->setSubtitle("Issue Refund");
            
            //Validate receipt
            $receipt = $this->parent->db->query("SELECT * FROM `receipt` WHERE receipt_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if (!$receipt) $this->error("Invalid Product ID");
            
            //Get purchases for receipt
            $receipt["purchases"] = array();
            $purchases = $this->parent->db->query("SELECT purchase.purchase_id,purchase.product_id,purchase.price,purchase.refunded,product.product_name FROM `purchase`,`product` WHERE purchase.receipt_id = '%s' AND purchase.product_id = product.product_id", $receipt["receipt_id"]);
            $receipt["total"] = 0;
            $refunded = true;
            while ($purchase = $purchases->fetch_assoc()) {
                if ($purchase["refunded"] == 0) $refunded = false;
                $receipt["total"] += $purchase["price"];
                $purchase["price"] = money_format("%i", $purchase["price"]);
                $receipt["purchases"][] = $purchase;
            }
            $receipt["total"] = money_format("%i", $receipt["total"]);
            
            //If all purchases already refunded, error
            if ($refunded) $this->error("All purchases for this receipt have already been refunded");
            
            $data["receipt"] = $receipt;

            $this->parent->template->outputHeader();
            $this->parent->template->outputTemplate('refund', $data);
            $this->parent->template->outputFooter();
            
        }
        
        public function actionSubmit() {
        
            $this->parent->auth->requireLogin();
            $userdata = $this->parent->auth->getUserData();
        
            /********************/
            // VALIDATION
            /********************/
            if ($this->inputs["comments"] == "Comments") $this->inputs["comments"] = "";
            
            //Validate receipt
            $receipt = $this->parent->db->query("SELECT * FROM `receipt` WHERE receipt_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if (!$receipt) $this->errorJSON("Invalid Product ID");
            
            //Validate purchases
            $purchases = array();
            $lanPurchases = array();
            foreach ($this->inputs["purchases"] as $purchaseID) {
                $purchase = $this->parent->db->query("SELECT purchase.purchase_id,purchase.receipt_id,purchase.product_id,purchase.price,product.product_name,product.event_id FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND purchase_id = '%s'", $purchaseID)->fetch_assoc();
                if (!$purchase) $this->errorJSON("Invalid purchase ID");
                if ($purchase["receipt_id"] != $receipt["receipt_id"]) $this->errorJSON("Purchase is not assigned to specified receipt ID");
                $purchases[] = $purchase;
                
                //LAN ticket checking
                if ($purchase["event_id"] != "") {
                    //Check if event is a LAN
                    $event = $this->parent->db->query("SELECT * FROM `event` WHERE event_id = '%s'", $purchase["event_id"])->fetch_assoc();
                    if ($event && preg_match("/^LAN(\d\d+?)$/", $event["event_name"], $matches) == 1) {
                        $lanPurchases[] = $purchase["purchase_id"];
                    }
                }
                
            }
            
            
            /********************/
            // SEND EMAIL
            /********************/
            $email = new EmailWrapper($this->parent);
            $email->loadTemplate('refund');
            $email->setTo($receipt["email"]);
            $email->setSubject("Refund for your purchase from LSU Computer Society");
            
            //Format product table for email
            $prodTable = "<table style='border-color: black;'><tr style='background-color: #999;'><td style='padding: 5px' width='200px'>Product</td><td style='padding: 5px' width='100px'>Price</td></tr>";
            $total = 0;
            foreach ($purchases as $key => $product) {
                $prodTable .= "<tr style='background-color: #e8e8e8; color: black;'><td style='padding: 2px;' width='200px'>" . $product["product_name"] . "</td><td style='padding: 2px;' width='100px'>&pound;" . $product["price"] . "</td></tr>";
                $total += $product["price"];
            }
            $prodTable .="</table>";
            $total = "&pound;" . number_format($total, 2);
            
            //Format template
            $email->replaceKey("%CUSTNAME%", $receipt["name"]);
            $email->replaceKey("%CUSTEMAIL%", $receipt["email"]);
            $email->replaceKey("%CUSTID%", ($receipt["student_id"] == null?"N/A":$receipt["student_id"]));
            $email->replaceKey("%ORDERDATE%", $receipt["date"]);
            $email->replaceKey("%FORUMNAME%", $receipt["customer_forum_name"]);
            $email->replaceKey("%PRODTABLE%", $prodTable);
            $email->replaceKey("%REFUNDTOTAL%", $total);
            $email->replaceKey("%REFUNDCOMMENTS%", ($this->inputs["comments"] == ""?"None":$this->inputs["comments"]));
            $email->replaceKey("%ISSUER%", $userdata["username"]);
            
            //Send
            if (!$email->send()) {
                $this->errorJSON("Unable to send e-receipt - is the email address correct?");
            }
            
            
            /********************/
            // LAN API
            /********************/
            if (count($lanPurchases) > 0) {
            
                //Set up field values
                $fields = array("api_key" => $this->parent->config->api["api_key"],
                                "purchases" => json_encode($lanPurchases));   
                $fields_string = "";
                foreach($fields as $key=>$value) $fields_string .= $key.'='.$value.'&';
                rtrim($fields_string, '&');
                
                //Set up cURL and request                       
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $this->parent->config->api["lan_api_refund_url"]);
                curl_setopt($ch,CURLOPT_POST, count($fields));
                curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
                $result = json_decode(curl_exec($ch), true);
                
                //If error
                if (isset($result["error"]) || !isset($result["success"])) {
                    $this->rollback($receiptID);
                    $this->errorJSON(!isset($result["success"])?$result:$result["error"]);
                }
            }
            
            
            /********************/
            // QUERIES
            /********************/   
            $this->parent->db->query("INSERT INTO `refund` (receipt_id, issuer_forum_name, comments) VALUES ('%s', '%s', '%s')", $receipt["receipt_id"], $userdata["username"], $this->inputs["comments"]);
            $refundID = $this->parent->db->getLink()->insert_id;
            
            //Update purchases
            foreach ($purchases as $purchase) {
                $this->parent->db->query("UPDATE `purchase` SET refunded = 1, refund_id = '%s' WHERE purchase_id = '%s'", $refundID, $purchase["purchase_id"]);
            }
            
            //Update receipt
            $this->parent->db->query("UPDATE `receipt` SET refunded = 1 WHERE receipt_id = '%s'", $receipt["receipt_id"]);
            
        }
    
    }
    
?>