<?php

    class EmailWrapper {
    
        private $template = "";
        private $message;
        private $transport;
        private $mailer;
        private $config;
        
        public function __construct($parent) {
        
            //Require Swift Mailer
            require_once 'lib/swift_required.php';
            $this->config = $parent->config->email;
           
            //Set up message
            $this->message = Swift_Message::newInstance();

        }
        
        //Template management
        public function loadTemplate($template) {
            $this->template = file_get_contents("emails/" . $template . ".html");
        }
        
        public function replaceKey($key, $value) {
            $this->template = str_replace($key, $value, $this->template);
        }
        
        //Settings
        public function setTo($value) {
            $this->message->setTo($value);
        }
        public function setSubject($value) {
            $this->message->setSubject($value);
        }
        
        //Send message
        public function send() {
        
            //Set up transport
            $this->transport = Swift_SmtpTransport::newInstance($this->config["host"], $this->config["port"]);
            $this->transport->setUsername($this->config["user"]);
            $this->transport->setPassword($this->config["pass"]);
            
            //Set up mailer
            $mailer = Swift_Mailer::newInstance($this->transport);
            
            //Set message properties
            $this->message->setContentType("text/html");
            $this->message->setCharset("iso-8859-1");
            $this->message->setBcc($this->config["cc"]);
            $this->message->setFrom(array($this->config["user"] => "LSU Computer Society"));
            $this->message->setBody($this->template);
            
            //Send and return
			try {
				return $result = $mailer->send($this->message);
			} catch (Exception $e) {
				file_put_contents("email-fail.txt", $e->getMessage());
				return false;
			}
        
        }
    
    }

?>