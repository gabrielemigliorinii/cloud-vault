<?php


    require_once __DIR__ . '/../../resource/http_response.php';
    require_once __DIR__ . '/../../resource/http_response.php';
    require_once __DIR__ . '/../../resource/file_system_handler.php';
    require_once __DIR__ . '/../../resource/token.php';
    require_once __DIR__ . '/../../resource/crypto.php';
    require_once __DIR__ . '/../../resource/mypdo.php';
    require_once __DIR__ . '/../../resource/mail.php';
    require_once __DIR__ . '/../../resource/mydatetime.php';
    require_once __DIR__ . '/../../resource/client.php';
    require_once __DIR__ . '/../model/user.php';
    require_once __DIR__ . '/../model/email_verify.php';
    require_once __DIR__ . '/../model/user_security.php';
    require_once __DIR__ . '/../model/session.php';
    require_once __DIR__ . '/../view/assets/navbar.php';
    
    class SigninController
    {
        public static function render_signin_page($success_msg = "", $error_msg = "", $redirect = "")
        {
            $navbar = Navbar::getPublic('signin');
            include __DIR__ . "/../view/signin.php";
        }

        public static function process_signin($email, $pwd)
        {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                http_response::client_error(400, "Invalid email format");

            $user = new User(email:$email);

            $id_user = $user->sel_id_from_email();
            
            // There's no email in db that is equals to $user->get_email()
            if ($id_user === -1)
                http_response::client_error(400, "That email doesn't exists in our system");

            
            // email $user->get_email() exists in db
            
            $user->set_id($id_user);

            // get hashed pwd from db

            $us = new UserSecurity(id_user: $id_user);
            $pwd_hash = $us->sel_pwd_hash_from_id();

            // there's no record in user_security that has that id_user, server error 
            if ($pwd_hash === -1)
                http_response::server_error(500, "Something wrong, try again");

            $us->set_pwd_hash($pwd_hash);

            // password is wrong (1FA FAILED)
            if (!password_verify($pwd, $us->get_pwd_hash()))
                http_response::client_error(400, "Password is wrong");

            session_start();

            $verified = $user->sel_verified_from_id();

            if ($verified === null)
            {
                session_destroy();
                http_response::server_error();
            }

            // user is tryin' to login without have verified the email 
            if ($verified === 0)
            {
                $_SESSION['VERIFY_PAGE_STATUS'] = 'SIGNIN_WITH_EMAIL_NOT_VERIFIED';
                $_SESSION['EMAIL'] = $user->get_email();

                http_response::client_error
                (
                    400, 
                    "Confirm your email before sign in", 
                    array("redirect" => '/verify')
                );
            }

            // user is verified

            $_SESSION['AUTH_1FA'] = true;
            $_SESSION['ID_USER'] = $user->get_id();

            if (isset($_SESSION['VERIFY_PAGE_STATUS'])) unset($_SESSION['VERIFY_PAGE_STATUS']);

            // check if 2FA is setted

            $p2fa = $user->sel_2fa_from_id();

            if ($p2fa === null)
            {
                session_destroy();
                http_response::server_error(500);
            }

            $us->set_id_user($user->get_id());
            $dkey_salt = $us->sel_dkey_salt_from_id();
            $_SESSION['DKEY'] = crypto::deriveKey($pwd, $dkey_salt);

            // User has 2FA active, redirect to 2FA page
            if ($p2fa === 1)
            {
                $_SESSION['OTP_CHECKING'] = true;

                http_response::successful(
                    200, 
                    false, 
                    array("redirect" =>  '/auth2')
                );
            }

            // No 2FA, login ok

            $_SESSION['LOGGED'] = true;

            // check if there is an active session with the client IP

            Session::create_or_load(client::get_ip(), $user->get_id());

            http_response::successful
            (
                200, 
                false, 
                array("redirect" =>  '/clouddrive')
            );
        }

        public static function process_signout()
        {
            if (session_status() == PHP_SESSION_NONE)                
                session_start();

            $_SESSION = [];
            session_destroy();

            http_response::redirect('/signin');
        }
    }

?>