<?php

    require_once __DIR__ . '/../../resource/http/http_response.php';
    require_once __DIR__ . '/../../resource/storage/file_sys_handler.php';
    require_once __DIR__ . '/../../resource/security/crypto_rnd_string.php';
    require_once __DIR__ . '/../../resource/storage/mypdo.php';
    require_once __DIR__ . '/../../resource/mymail.php';
    require_once __DIR__ . '/../../resource/security/my_tfa.php';
    require_once __DIR__ . '/../../resource/security/user_keys_handler.php';
    require_once __DIR__ . '/../view/assets/navbar.php';
    require_once __DIR__ . '/../model/user.php';
    require_once __DIR__ . '/../model/email_verify.php';
    require_once __DIR__ . '/../model/user_secrets.php';
    
    class SignupController
    {
        public static function renderSignupPage()
        {
            $navbar = Navbar::getPublic('signup');
            include __DIR__ . '/../view/signup.php';
        }

        public static function renderSignupSuccessPage()
        {
            include __DIR__ . '/../view/static/signup_success.php';
        }

        public static function processSignup($email, $pwd, $name, $surname)
        {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                httpResponse::clientError(400, "Invalid email format");

            if (strlen($pwd) < 1)
                httpResponse::clientError(400, "Password too short");

                

            // ----------- BEGIN User CREATION -------------

            $user = new UserModel(email:$email, name:$name, surname:$surname);

            $email_is_taken = $user->email_is_taken();

            if ($email_is_taken === 1)
            {
                httpResponse::clientError(400, "Email already taken");
            }
            else if ($email_is_taken === 0)
            {
                // email is available
            }
            else
                httpResponse::serverError();

            MyPDO::connect('insert');
            MyPDO::beginTransaction();
            
            if (!$user->ins())
            {
                MyPDO::rollBack();
                httpResponse::serverError();
            }

            $user->sel_userID_by_email();

            // Creation of User Storage dir 
            $user_dir_created = FileSysHandler::makeUserDir($user->getUserID(), $user->getEmail());
            if (!$user_dir_created)
            {
                MyPDO::rollBack();
                HttpResponse::serverError();
            }
            
            // ----------- END User CREATION -------------


            
            // ----------- BEGIN User-Secrets CREATION -------------


            $user_keys = UserKeysHandler::getInstanceFromPassword($pwd);
            
            $user_secrets_data = new UserSecretsModel
            (
                password_hash:         $user_keys->getPasswordHashed(),
                recoverykey_hash:      $user_keys->getRecoveryKeyHashed(),
                recoverykey_encrypted: $user_keys->getRecoveryKeyEncrypted(),
                cipherkey_encrypted:   $user_keys->getCipherKeyEncrypted(),
                secret2fa_encrypted:   $user_keys->getSecret2FAEncrypted(),
                masterkey_salt:        $user_keys->getMasterKeySalt(),
                id_user:               $user->getUserID()
            );

            if (!$user_secrets_data->ins())
            {
                MyPDO::rollBack();
                httpResponse::serverError();
            }

            // ----------- END User-Secrets CREATION -------------




            // ----------- BEGIN Email-Verify CREATION -------------

            $email_sent = true;//EmailVerifyController::send_email_verify($user->get_email());

            if ($email_sent === false)
            {
                MyPDO::rollBack();
                httpResponse::clientError(400, "There is an issue with the provided email address, it may not exist.");
            }
            
            // ----------- END Email-Verify CREATION -------------

            


            // Transaction OK
            MyPDO::commit();

            httpResponse::successful
            (
                status_code:    201, 
                response_array: ["redirect" => '/signup/success']
            );
        }
    }


?>
