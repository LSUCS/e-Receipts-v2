<?php

    class Auth {
    
        private $parent;
        private $userModel;
        private $userLevel = UserLevels::Guest;
        
        /**
         * Constructor - loads session and logs user in if possible
         */
        public function __construct($parent) {
            $this->parent = $parent;
            
            //Initiate XenForo
            $this->xfDir = $this->parent->config->auth["xenforoDir"];
            $this->startTime = microtime(true);
            require($this->xfDir . '/library/XenForo/Autoloader.php');
            require("/home/soc_lsucs/lsucs.org.uk/htdocs/library/XenForo/Session.php");
            XenForo_Autoloader::getInstance()->setupAutoloader($this->xfDir. '/library');
            XenForo_Application::initialize($this->xfDir . '/library', $this->xfDir);
            XenForo_Application::set('page_start_time', $this->startTime);
            $this->userModel = XenForo_Model::create('XenForo_Model_User');
            
            //Initiate XenForo sessions
            $session = new Xenforo_Session();
            $session->startPublicSession();
            
            //Calculate user level
            $this->storeUserLevel();
        }
        
        /**
         * Logs the user in
         */
        public function loginUser($username, $password) {
            //If no cookies, return false
            if (count($_COOKIE) == 0) return false;
            
            //Check credentials
            $userId = $this->userModel->validateAuthentication($username, $password, $error);
            if (!$userId) return false;
            
            //Setup session
            $this->userModel->setUserRememberCookie($userId);
            $this->userModel->deleteSessionActivity(0, $this->getIp());
            $session = XenForo_Application::get('session');
            $session->changeUserId($userId);
            XenForo_Visitor::setup($userId);
            
            //Calculate user level
            $this->storeUserLevel();
            
            return true;
        }
        
        /**
         * Logs the user out
         */
        public function logoutUser() {
            //Remove an admin session if we're logged in as the same person
			if (XenForo_Visitor::getInstance()->get('is_admin'))
			{
				$adminSession = new XenForo_Session(array('admin' => true));
				$adminSession->start();
				if ($adminSession->get('user_id') == Xenforo_Visitor::getInstance()->getUserId())
				{
					$adminSession->delete();
				}
			}
            
            //Clear down normal sessions
            $sessionModel = XenForo_Model::create("XenForo_Model_Session");
			$sessionModel->processLastActivityUpdateForLogOut(XenForo_Visitor::getUserId());
			XenForo_Application::get('session')->delete();
			XenForo_Helper_Cookie::deleteAllCookies(
				array('session'),
				array('user' => array('httpOnly' => false))
			);
            
            //Setup guest user
			XenForo_Visitor::setup(0);
            $this->storeUserLevel();
        }
        
        /**
         * User Level Access Requirements
         */
        public function requireLogin() {
            if ($this->userLevel == UserLevels::Guest) {
                header("location:index.php?page=account&action=login&returnurl=" . urlencode($_SERVER['REQUEST_URI']));
                return;
            }
        }
        public function requireNotLoggedIn() {
            if ($this->isLoggedIn()) {
                header("location:index.php");
            }
        }
        public function requireAdmin() {
            if (!$this->userLevel == UserLevels::Admin) {
                header("location:index.php?page=account&action=login&returnurl=" . urlencode($_SERVER['REQUEST_URI']));
            }
        }
        
        /**
         * Returns user's email address
         */
        public function getUserData() {
            if ($this->userLevel == UserLevels::Guest) return array();
            else return XenForo_Visitor::getInstance()->toArray();
        }
        
        /**
         * Gets the data for inputted user
         */
        public function getUserByName($username) {
            return $this->userModel->getUserByName($username);
        }
        
        /**
         * User Level Utilities
         */
        public function isAdmin() {
            return ($this->userLevel == UserLevels::Admin);
        }
        public function isGuest() {
            return ($this->userLevel == UserLevels::Guest);
        }
        public function isLoggedIn() {
            return !($this->userLevel == UserLevels::Guest);
        }
        
        /**
         * Works out and stores the current user level according to the user signed in
         */
        private function storeUserLevel() {
            $group = $this->parent->config->auth["xenforo_committee_group_id"];
            if (XenForo_Visitor::getInstance()->get("is_admin")) {
                $this->userLevel = UserLevels::Admin;
            } else if (XenForo_Visitor::getInstance()->get("user_group_id") == $group || in_array($group, explode(",", XenForo_Visitor::getInstance()->get("user_group_id")))) {
                $this->userLevel = UserLevels::Regular;
            } else {
                $this->userLevel = UserLevels::Guest;
            }
        }
        
        /**
         * Utility function to get the most accurate IP
         */
        private function getIp() {
          if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
          else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
          else $ip= $_SERVER['REMOTE_ADDR'];
          return $ip;
        }
    
    }
    
    abstract class UserLevels {
        const Admin = 0;
        const Regular = 1;
        const Guest = 2;
    }

?>