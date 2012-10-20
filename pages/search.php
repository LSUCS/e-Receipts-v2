<?php

    class Search_Page extends Page {
    
        public function getInputs() {
            return array(
                        "actionDelete" => array("id" => "post"),
                        "actionGetreceipt" => array("id" => "post"),
                        "actionSearch" => array("search_type" => "post", "search_value" => "post", "product" => "post", "event" => "post", "refunded" => "post", "by_date" => "post", "start_date" => "post", "end_date" => "post")
 
                        );
        }
    
        public function actionIndex() {
        
            $this->parent->auth->requireLogin();
            
            $this->parent->template->setSubtitle("Search Receipts");
            $this->parent->template->outputHeader();
            $data = array();
            $res = $this->parent->db->query("SELECT * FROM `event");
            while ($row = $res->fetch_assoc()) $data["events"][] = $row;
            $this->parent->template->outputTemplate("search", $data);
            $this->parent->template->outputFooter();
            
        }
        
        public function actionDelete() {
            
            $this->parent->auth->requireLogin();
            
            /********************/
            // VALIDATION
            /********************/
            $receipt = $this->parent->db->query("SELECT * FROM `receipt` WHERE receipt_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if (!$receipt) $this->errorJSON("Invalid receipt ID");
            
            //Check date
            if (strtotime($receipt["date"]) < strtotime("-2 days")) {
                //$this->errorJSON("Cannot delete receipts issued more than 2 days ago");
            }
            
            //Check for refund
            if ($receipt["refunded"] == 1) {
                $this->errorJSON("Cannot delete refunded receipts");
            }
            
            //Get purchases
            $purchases = array();
            $lanPurchases = array();
            $res = $this->parent->db->query("SELECT purchase.product_id,purchase.price,purchase.refunded,product.product_name,product.event_id,purchase.purchase_id FROM `purchase`,`product` WHERE purchase.receipt_id = '%s' AND purchase.product_id = product.product_id", $receipt["receipt_id"]);
            while ($purchase = $res->fetch_assoc()) {
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
            
            $userdata = $this->parent->auth->getUserData();
            
            
            /********************/
            // SEND EMAIL
            /********************/
            $email = new EmailWrapper($this->parent);
            $email->loadTemplate('delete');
            $email->setTo($receipt["email"]);
            $email->setSubject("Your receipt from LSU Computer Society has been deleted");
            
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
            $email->replaceKey("%ORDERDATE%", date('l jS \of F Y h:i:s A'));
            $email->replaceKey("%FORUMNAME%", $receipt["customer_forum_name"]);
            $email->replaceKey("%PRODTABLE%", $prodTable);
            $email->replaceKey("%ORDERTOTAL%", $total);
            $email->replaceKey("%ORDERCOMMENTS%", ($receipt["comments"] == ""?"None":$receipt["comments"]));
            $email->replaceKey("%ISSUER%", $receipt["issuer_forum_name"]);
            $email->replaceKey("%DELETER%", $userdata["username"]);
            
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
                    $this->errorJSON(!isset($result["success"])?$result:$result["error"]);
                }
            }
            
            
            /********************/
            // Queries
            /********************/
            $this->parent->db->query("DELETE FROM `receipt` WHERE `receipt_id` = '%s'", $receipt["receipt_id"]);
            $this->parent->db->query("DELETE FROM `purchase` WHERE `receipt_id` = '%s'", $receipt["receipt_id"]);
            
        }
        
        public function actionGetreceipt() {
        
            $this->parent->auth->requireLogin();
        
            //Get receipt
            $receipt = $this->parent->db->query("SELECT * FROM `receipt` WHERE receipt_id = '%s'", $this->inputs["id"])->fetch_assoc();
            if (!$receipt) $this->errorJSON("Invalid receipt ID");
            
            //Get purchases for receipt
            $receipt["purchases"] = array();
            $purchases = $this->parent->db->query("SELECT purchase.product_id,purchase.price,purchase.refunded,product.product_name FROM `purchase`,`product` WHERE purchase.receipt_id = '%s' AND purchase.product_id = product.product_id", $receipt["receipt_id"]);
            $receipt["total"] = 0;
            while ($purchase = $purchases->fetch_assoc()) {
                $receipt["total"] += $purchase["price"];
                $purchase["price"] = money_format("%i", $purchase["price"]);
                $receipt["purchases"][] = $purchase;
            }
            
            //Get refunds for receipt
            $receipt["refunds"] = array();
            $refunds = $this->parent->db->query("SELECT * FROM `refund` WHERE receipt_id = '%s'", $receipt["receipt_id"]);
            while ($refund = $refunds->fetch_assoc()) {
                //Get purchases for refund
                $purchases = $this->parent->db->query("SELECT purchase.product_id,purchase.price,purchase.refunded,product.product_name FROM `purchase`,`product` WHERE purchase.receipt_id = '%s' AND refunded = 1 AND refund_id = '%s' AND purchase.product_id = product.product_id", $receipt["receipt_id"], $refund["refund_id"]);
                $refund["total"] = 0;
                while ($purchase = $purchases->fetch_assoc()) {
                    $refund["total"] += $purchase["price"];
                    $purchase["price"] = "£" . money_format("%.2n", $purchase["price"]);
                    $refund["purchases"][] = $purchase;
                }
                //Add to receipt
                $receipt["refunds"][] = $refund;
            }
            
            //Output
            echo json_encode($receipt);
        }
        
        public function actionSearch() {
        
            $this->parent->auth->requireLogin();
        
            //Sort out conditions clause
            $conditions = array();
            if ($this->inputs["search_value"] != "") {
                $conditions[] = $this->parent->db->clean($this->inputs["search_type"]) . " LIKE '%" . $this->parent->db->clean($this->inputs["search_value"]) . "%'";
            }
            if ($this->inputs["refunded"] == "true" || $this->inputs["refunded"] == 1) {
                $conditions[] = "refunded = 1";
            } else $conditions[] = "refunded = 0";
            if ($this->inputs["by_date"] == "true" || $this->inputs["by_date"] == 1) {
                if ($this->inputs["start_date"] != "") {
                    $conditions[] = "date >= '" . $this->parent->db->clean($this->inputs["start_date"]) . "'";
                }
                if ($this->inputs["end_date"] != "") {
                    $conditions[] = "date <= '" . $this->parent->db->clean($this->inputs["end_date"]) . "'";
                }
            }
            
            //Products
            $products = array();
            if ($this->inputs["product"] != "") {
                $res = $this->parent->db->query("SELECT * FROM `product` WHERE product_name LIKE '%%%s%%'", $this->inputs["product"]);
                while ($row = $res->fetch_assoc()) $products[$row["product_id"]] = $row;
            }
            
            //Query
            $query = "SELECT * FROM `receipt`";
            if (count($conditions) > 0) $query .= " WHERE " . implode(" AND ", $conditions);
            $res = $this->parent->db->query($query);
            $results = array();
            while ($row = $res->fetch_assoc()) {
            
                $validProduct = false;
                $validEvent = false;
            
                //Get purchases for receipt and calculate total
                $res2 = $this->parent->db->query("SELECT purchase.product_id,purchase.price,product.product_name,product.event_id FROM `purchase`,`product` WHERE purchase.product_id = product.product_id AND receipt_id = '%s'", $row["receipt_id"]);
                $row["total"] = 0;
                $row["products"] = array();
                while ($purchase = $res2->fetch_assoc()) {
                    //Check valid product
                    if (isset($products[$purchase["product_id"]])) $validProduct = true;
                    //Check valid event
                    if ($this->inputs["event"] != "" && $purchase["event_id"] == $this->inputs["event"]) $validEvent = true;
                    //Add to product amount
                    if (isset($row["products"][$purchase["product_name"]])) $row["products"][$purchase["product_name"]]++;
                    else $row["products"][$purchase["product_name"]] = 1;
                    $row["total"] += $purchase["price"];
                }
                
                //Check for valid product if searching by products
                if (count($products) > 0 && !$validProduct) continue;
                
                //Check for valid event if searching by event
                if ($this->inputs["event"] != "" && !$validEvent) continue;
                
                //Form array for datatables
                $productarr = array();
                foreach ($row["products"] as $product => $amount) {
                    if ($amount == 1) $productarr[] = $product;
                    else $productarr[] = $product . " x" . $amount;
                }
                $receipt = array(
                            $row["receipt_id"],
                            $row["date"],
                            $row["name"],
                            $row["email"],
                            $row["issuer_forum_name"],
                            implode(", ", $productarr)
                            );

                
                $results[] = $receipt;

            }
            
            echo json_encode($results);
            
        }
        
    }

?>