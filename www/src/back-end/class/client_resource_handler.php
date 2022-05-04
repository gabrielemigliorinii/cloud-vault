<?php

    require_once "response.php";
    require_once "sqlc.php";

    if (isset($_SERVER['REQUEST_METHOD']))
    {
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'GET':
            {
                if (isset($_GET['DATA']) && count($_GET) === 1)
                {
                    session_start();
                    $id_user = $_SESSION['ID_USER'];
                    sqlc::connect();
                    $email = sqlc::get_email($id_user);
                    $rep = md5("dir" . $id_user . $email);
                    $files = scandir("../users/$rep");
                    response::successful(200, false, array("files" => $files, "rep" => $rep));
                }
                else if (isset($_GET['FILE']) && isset($_GET['REP']) && count($_GET) === 2)
                {
                    $ctx = file_get_contents("../users/" . $_GET['REP'] . "/" . $_GET['FILE']);
                    response::successful(200, false, array("ctx" => $ctx));
                }

                break;
            }
            case 'POST':
            {
                if (isset($_POST['NAM']) && isset($_POST['CTX']) && isset($_POST['SIZ']) && isset($_POST['IMP']) && count($_POST) === 4)
                {
                    // upload file
                    $filename = $_POST['NAM'];
                    $filedata = $_POST['CTX'];
                    $client_hash = $_POST['IMP'];
                    $server_hash = hash("sha256", $filename.$filedata);

                    if ($client_hash === $server_hash)
                    {
                        session_start();
                        sqlc::connect();
    
                        $id = $_SESSION['ID_USER'];
                        $email = sqlc::get_email($id);
                        $size = $_POST['SIZ'];
                        
                        $dir = md5("dir" . $id . $email);

                        file_put_contents("../users/{$dir}/{$filename}", $filedata);
                        //sqlc::upl_file($server_hash, $id, $size);
                        
                        response::successful(201);
                        exit;
                    }
                    else
                    {
                        // file alterato
                        response::server_error(500);
                    }
                }
                break;
            }
            default:
            {
                response::client_error(405);
                break;
            }
        }
    }
    else response::server_error(500);


?>
