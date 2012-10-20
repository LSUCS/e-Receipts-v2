<?php

    class Template {
    
        private $subtitle;
        private $navbar = array();
        private $parent;
        private $refresh = false;
        
        function __construct($parent) {
            $this->parent = $parent;
            $this->subtitle = $this->parent->config->page["default_title"];
        }
        
        function setRefresh($bool) {
            $this->refresh = $bool;
        }
        
        function setSubTitle($title) {
            $this->subtitle = $title;
        }
        
        function addNavElement($url, $name, $page) {
            $this->navbar[] = array($url, $name, $page);
        }
        
        function outputHeader() {
            $data["title"] = ucwords($this->parent->page) . " | LSUCS e-Receipts";
            $data["navbar"] = $this->navbar;
            $data["subtitle"] = $this->subtitle;
            $data["page"] = $this->parent->page;
            $data["refresh"] = $this->refresh;
            $data["loggedin"] = $this->parent->auth->isLoggedIn();
            $data["user"] = $this->parent->auth->getUserData();
            $this->outputTemplate("header", $data);
        }
        
        function outputFooter() {
            $this->outputTemplate("footer");
        }
        
        function outputTemplate($template, $DataBag = "") {
            include("templates/" . $template . ".tmpl");
        }
    
    }

?>