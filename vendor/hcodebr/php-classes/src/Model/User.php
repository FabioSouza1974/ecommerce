<?php

namespace Hcode\Model;

use Hcode\DB\Sql;
use Hcode\Mailer;
use Hcode\Model;

class User extends Model
{
    public const SESSION = 'User';
    public const SECRET = 'mpIT@Dev@2087';
    public const METHOD = 'aes-128-cbc';
    public const ERROR = 'UserError';
    public const ERROR_REGISTER = 'UserErrorRegister';
    public const SUCCESS = 'UserSuccess';

    public static function getFromSession()
    {
        $user = new User();

        if (isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0) {
            $user->setData($_SESSION[User::SESSION]);
        }

        return $user;
    }

    public static function checkLogin($inadmin = true)
    {
        if (!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]['iduser'] > 0) {
            return false; //Não esta logado
        } else {
            if ($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true) {
                return true;
            } elseif ($inadmin === false) {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select('SELECT * FROM tb_users a INNER JOIN tb_persons b ON a.idperson = b.idperson WHERE deslogin = :LOGIN', [
            ':LOGIN' => $login,
        ]);

        if (count($results) === 0) {
            throw new \Exception('Usuário inexistente ou senha inválida.');
        }

        $data = $results[0];

        if (password_verify($password, $data['despassword']) === true) {
            $user = new User();

            //$data['desperson'] = utf8_encode($data['desperson']);
            $data['desperson'] = mb_convert_encoding($data['desperson'], 'UTF-8', mb_detect_encoding($data['desperson']));

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        } else {
            throw new \Exception('Usuário inexistente ou senha inválida.');
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if (!User::checkLogin($inadmin)) {
            if ($inadmin) {
                header('Location: /admin/login');
            } else {
                header('Location: /login');
            }
        }
        exit;
    }

    public static function logout()
    {
        $_SESSION[User::SESSION] = null;
        header('Location: /admin/login');
        exit;
    }

    public static function listAll()
    {
        $sql = new Sql();

        return $sql->select('SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson');
    }

    public function save()
    {
        $sql = new Sql();

        $results = $sql->select('CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)', [
            ':desperson' => mb_convert_encoding($this->getiduser(), 'UTF-8'), //utf8_decode($this->getdesperson()), // mb_convert_encoding($utf8_string, 'ISO-8859-1', 'UTF-8');
            ':deslogin' => $this->getdeslogin(),
            ':despassword' => User::getPasswordHash($this->getdespassword()),
            ':desemail' => $this->getdesemail(),
            ':nrphone' => $this->getnrphone(),
            ':inadmin' => $this->getinadmin(),
        ]);

        $this->setData($results[0]);
    }

    public function get($iduser)
    {
        $sql = new Sql();

        $results = $sql->select('SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser', [
            ':iduser' => $iduser,
        ]);

        $data = $results[0];

        //$data['desperson'] = utf8_encode($data['desperson']);
        $data['desperson'] = mb_convert_encoding($data['desperson'], 'UTF-8', mb_detect_encoding($data['desperson']));

        $this->setData($data);
    }

    public function update()
    {
        $sql = new Sql();

        $results = $sql->select('CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)', [
            ':iduser' => mb_convert_encoding($this->getiduser(), 'UTF-8'), //utf8_decode($this->getiduser()) // mb_convert_encoding($utf8_string, 'ISO-8859-1', 'UTF-8');
            ':desperson' => $this->getdesperson(),
            ':deslogin' => $this->getdeslogin(),
            ':despassword' => User::getPasswordHash($this->getdespassword()),
            ':desemail' => $this->getdesemail(),
            ':nrphone' => $this->getnrphone(),
            ':inadmin' => $this->getinadmin(),
        ]);

        $this->setData($results[0]);
    }

    public function delete()
    {
        $sql = new Sql();

        $sql->query('CALL sp_users_delete(:iduser)', [
            ':iduser' => $this->getiduser(),
        ]);
    }

    public static function getForgot($email, $inadmin = true)
    {
        $sql = new Sql();
        $results = $sql->select('SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email', [
            ':email' => $email,
        ]);

        if (count($results) === 0) {
            throw new \Exception('Não foi possível recuperar a senha.');
        } else {
            $data = $results[0];

            $results2 = $sql->select('CALL sp_userspasswordsrecoveries_create(:iduser, :desip)', [
                ':iduser' => $data['iduser'],
                ':desip' => $_SERVER['REMOTE_ADDR'],
            ]);

            if (count($results2) === 0) {
                throw new \Exception('Não foi possível recuperar a senha.');
            } else {
                $dataRecovery = $results2[0];
                $ivlen = openssl_cipher_iv_length(User::METHOD);
                $iv = openssl_random_pseudo_bytes($ivlen);
                $code = openssl_encrypt($dataRecovery['idrecovery'], User::METHOD, User::SECRET, 0, $iv);
                if ($inadmin == true) {
                    $link = 'http://www.hcodecommerce.com.br/admin/forgot/reset?code=' . $code;
                } else {
                    $link = 'http://www.hcodecommerce.com.br/forgot/reset?code=' . $code;
                }
                $mailer = new Mailer($data['desemail'], $data['desperson'], 'Redefinir Senha Hcode Store', 'forgot', [
                    'name' => $data['desperson'],
                    'link' => $link,
                ]);
                $mailer->send();

                return $data;
            }
        }
    }

    public static function validForgotDecrypt($code)
    {
        $ivlen = openssl_cipher_iv_length(User::METHOD);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $idrecovery = openssl_decrypt(base64_decode($code), User::METHOD, User::SECRET, 0, $iv);
        $sql = new Sql();
        $results = $sql->select('SELECT * FROM tb_userspasswordsrecoveries a INNER JOIN tb_users b USING(iduser) 
        INNER JOIN tb_persons c USING(idperson) WHERE a.idrecovery = :idrecovery AND a.dtrecovery IS NULL AND DATE_ADD(a.dtregister,INTERVAL 1 HOUR) >= NOW();', [
            ':idrecovery' => $idrecovery,
        ]);

        if (count($results) === 0) {
            throw new \Exception('Não foi possivel recuperar a senha');
        } else {
            return $results[0];
        }
    }

    public static function setForgotUsed($idrecovery)
    {
        $sql = new Sql();
        $sql->query('UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery', [
            ':idrecovery' => $idrecovery,
        ]);
    }

    public function setPassword($password)
    {
        $sql = new Sql();
        $sql->query('UPDATE tb_users SET despassword = :password WHERE iduser = :iduser', [
            ':password' => $password,
            ':iduser' => $this->getiduser(),
        ]);
    }

    public static function setError($msg)
    {
        $_SESSION[User::ERROR] = $msg;
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR]) ? $_SESSION[User::ERROR] : '';

        USer::clearError();

        return $msg;
    }

    public static function clearError()
    {
        $_SESSION[User::ERROR] = null;
    }

    public static function setSuccess($msg)
    {
        $_SESSION[User::SUCCESS] = $msg;
    }

    public static function getSuccess()
    {
        $msg = (isset($_SESSION[User::SUCCESS]) && $_SESSION[User::SUCCESS]) ? $_SESSION[User::SUCCESS] : '';

        USer::clearSuccess();

        return $msg;
    }

    public static function clearSuccess()
    {
        $_SESSION[User::SUCCESS] = null;
    }

    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {
        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';
        USer::clearErrorRegister();

        return $msg;
    }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = null;
    }

    public static function checkLoginExist($login)
    {
        $sql = new Sql();
        $results = $sql->select('SELECT * FROM tb_users WHERE deslogin = :deslogin', [
            ':deslogin' => $login,
        ]);

        return (count($results) > 0);
    }

    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost' => 12,
        ]);
    }

    public function getOrders()
    {
        $sql = new Sql();
        $results = $sql->select('SELECT * FROM tb_orders a INNER JOIN tb_ordersstatus b USING(idstatus) INNER JOIN tb_carts c USING(idcart) 
            INNER JOIN tb_users d ON d.iduser = a.iduser INNER JOIN tb_addresses e USING(idaddress) INNER JOIN tb_persons f ON f.idperson = d.idperson 
            WHERE a.iduser = :iduser', [
            ':iduser' => $this->getiduser(),
        ]);

        if (count($results) > 0) {
            return $results;
        }
    }

    public static function getPage($page = 1, $itemsPerPage = 10)
    {
        $start = ($page - 1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT SQL_CALC_FOUND_ROWS * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson LIMIT $start, $itemsPerPage");

        $resultTotal = $sql->select('SELECT found_rows() AS nrtotal;');

        return [
            'data' => $results,
            'total' => (int)$resultTotal[0]['nrtotal'],
            'pages' => ceil($resultTotal[0]['nrtotal'] / $itemsPerPage),
        ];
    }

    public static function getPageSearch($search, $page = 1, $itemsPerPage = 10)
    {
        $start = ($page - 1) * $itemsPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT SQL_CALC_FOUND_ROWS * 
            FROM tb_users a 
            INNER JOIN tb_persons b USING(idperson) 
            WHERE b.desperson LIKE :search OR b.desemail = :search OR a.deslogin LIKE :search 
            ORDER BY b.desperson 
            LIMIT $start, $itemsPerPage", [
            ':search' => '%' . $search . '%',
        ]);

        $resultTotal = $sql->select('SELECT found_rows() AS nrtotal;');

        return [
            'data' => $results,
            'total' => (int)$resultTotal[0]['nrtotal'],
            'pages' => ceil($resultTotal[0]['nrtotal'] / $itemsPerPage),
        ];
    }
}
