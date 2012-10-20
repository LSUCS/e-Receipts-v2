<?php

    class Account_Page extends Page {
        
        public function getInputs() {
            return array(
                        "actionIndex" => array("returnurl" => "get", "username" => "post"),
                        "actionLogin" => array("returnurl" => "get", "username" => "post"),
                        "actionAuthlogin" => array("password" => "post", "username" => "post", "returnurl" => "post")
                        );
        }
    
        public function actionIndex() {
            if ($this->parent->auth->isLoggedIn()) {
                $this->actionLogout();
            } else {
                header("location:index.php?page=account&action=login");
            }
        }
        
        public function actionLogin($invalid = false) {
            $this->parent->auth->requireNotLoggedIn();
            $this->parent->template->setSubTitle("Login");
            $this->parent->template->outputHeader();
            
            //Set up data and output template
            $DataBag = array();
            $DataBag["username"] = $this->inputs["username"];
            $DataBag["invalid"] = $invalid;
            $DataBag["returnurl"] = $this->inputs["returnurl"];
            $this->parent->template->outputTemplate("login", $DataBag);
            
            $this->parent->template->outputFooter();
        }
        
        public function actionLogout() {
            $this->parent->auth->requireLogin();
            $this->parent->auth->logoutUser();
            header("location:index.php");
        }
        
        public function actionAuthlogin() {
            $this->parent->auth->requireNotLoggedIn();
            if ($this->parent->auth->loginUser($this->inputs["username"], $this->inputs["password"])) {
                header("location:" . (strlen($this->inputs["returnurl"]) == 0?"index.php":$this->inputs["returnurl"]));
            } else {
                $this->actionLogin(true);
            }
        }
    
    }

?>