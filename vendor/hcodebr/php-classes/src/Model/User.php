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

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select('SELECT * FROM tb_users WHERE deslogin = :LOGIN', [
            ':LOGIN' => $login,
        ]);

        if (count($results) === 0) {
            throw new \Exception('Usuário inexistente ou senha inválida.');
        }

        $data = $results[0];

        if (password_verify($password, $data['despassword']) === true) {
            $user = new User();

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        } else {
            throw new \Exception('Usuário inexistente ou senha inválida.');
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if (!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]['iduser'] > 0 || (bool)$_SESSION[User::SESSION]['inadmin'] !== $inadmin) {
            header('Location: /admin/login');
            exit;
        }
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
            ':desperson' => $this->getdesperson(),
            ':deslogin' => $this->getdeslogin(),
            ':despassword' => $this->getdespassword(),
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

        $this->setData($results[0]);
    }

    public function update()
    {
        $sql = new Sql();

        $results = $sql->select('CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)', [
            ':iduser' => $this->getiduser(),
            ':desperson' => $this->getdesperson(),
            ':deslogin' => $this->getdeslogin(),
            ':despassword' => $this->getdespassword(),
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
    public static function getForgot($email)
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
                $link = 'http://www.hcodecommerce.com.br/admin/forgot/reset?code=' . $code;
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
}
