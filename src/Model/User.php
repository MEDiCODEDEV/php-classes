<?php 

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model {

	const SESSION = "User";
	const FORGOT_SECRET = "HcodePhp7_Secret";

	public static function getFromSession($inadmin = true)
	{

		$user = new User();

		if (User::checkLogin($inadmin)) {

			$user->setData($_SESSION[User::SESSION]);

		}

		return $user;

	}
	
	public static function login($login, $password)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.deslogin = :LOGIN", array(
			":LOGIN"=>$login
		)); 

		if (count($results) === 0)
		{
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

		$data = $results[0];

		if (password_verify($password, $data["despassword"]) === true)
		{

			$user = new User();

			$user->setData($data);

			$_SESSION[User::SESSION] = $user->getValues();

			return $user;

		} else {
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

	}

	public static function checkLogin($inadmin = true)
	{

		if (
			!isset($_SESSION[User::SESSION])
			||
			!$_SESSION[User::SESSION]
			||
			!(int)$_SESSION[User::SESSION]["iduser"] > 0
		) {

			return false;

		} else {

			if ($inadmin === true && (bool)$_SESSION[User::SESSION]["inadmin"] === true) {
				return true;
			} else if ($inadmin === false) {
				return true;
			} else {
				return false;
			}

		}

	}

	public static function verifyLogin($inadmin = true)
	{

		if (!User::checkLogin($inadmin)) {
			
			if ($inadmin) {
				header("Location: /admin/login");
			} else {
				header("Location: /login");
			}

			exit;

		}

	}

	public static function logout()
	{

		$_SESSION[User::SESSION] = NULL;

	}

	public static function listAll()
	{

		$sql = new Sql();

		return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");

	}

	public function getSearchUsersPage($search, $page = 1, $itemsPerPage = 10)
	{

		$start = ($page - 1) * $itemsPerPage;
		$length = $itemsPerPage;

		$sql = new Sql();

		$rows = $sql->select("
			SELECT SQL_CALC_FOUND_ROWS *
			FROM tb_users a 
			INNER JOIN tb_persons b USING(idperson)
			WHERE deslogin LIKE :search OR desperson LIKE :search OR desemail LIKE :search
			ORDER BY b.desperson
			LIMIT $start, $length
		", [
			':search'=>'%'.utf8_decode($search).'%'
		]);

		$resultTotal = $sql->select("
			SELECT FOUND_ROWS() AS nrtotal
		");

		return [
			'data'=>$rows,
			'total'=>(int)$resultTotal[0]['nrtotal'],
			'pages'=>ceil($resultTotal[0]['nrtotal'] / $itemsPerPage)
		];

	}

	public function getUsersPage($page = 1, $itemsPerPage = 10)
	{

		$start = ($page - 1) * $itemsPerPage;
		$length = $itemsPerPage;

		$sql = new Sql();

		$rows = $sql->select("
			SELECT SQL_CALC_FOUND_ROWS *
			FROM tb_users a 
			INNER JOIN tb_persons b USING(idperson)
			ORDER BY b.desperson
			LIMIT $start, $length
		");

		$resultTotal = $sql->select("
			SELECT FOUND_ROWS() AS nrtotal
		");

		return [
			'data'=>$rows,
			'total'=>(int)$resultTotal[0]['nrtotal'],
			'pages'=>ceil($resultTotal[0]['nrtotal'] / $itemsPerPage)
		];

	}

	public function save()
	{

		$sql = new Sql();

		$results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
			":desperson"=>$this->getdesperson(),
			":deslogin"=>$this->getdeslogin(),
			":despassword"=>$this->getdespassword(),
			":desemail"=>$this->getdesemail(),
			":nrphone"=>$this->getnrphone(),
			":inadmin"=>$this->getinadmin()
		));

		$this->setData($results[0]);

	}

	public function get($iduser)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(
			":iduser"=>$iduser
		));

		$this->setData($results[0]);

	}

	public function update()
	{

		$sql = new Sql();

		$results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
			":iduser"=>$this->getiduser(),
			":desperson"=>$this->getdesperson(),
			":deslogin"=>$this->getdeslogin(),
			":despassword"=>$this->getdespassword(),
			":desemail"=>$this->getdesemail(),
			":nrphone"=>$this->getnrphone(),
			":inadmin"=>$this->getinadmin()
		));

		$this->setData($results[0]);		

	}

	public function delete()
	{

		$sql = new Sql();

		$sql->query("CALL sp_users_delete(:iduser)", array(
			":iduser"=>$this->getiduser()
		));

	}

	public function getForgot($email, $admin = true)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE b.desemail = :desemail", array(
			":desemail"=>$email
		));

		if (count($results) > 0)
		{

			$data = $results[0];

			$results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
				":iduser"=>$data['iduser'],
				":desip"=>$_SERVER["REMOTE_ADDR"]
			));

			$recoveryData = $results2[0];

			$encrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, User::FORGOT_SECRET, $recoveryData['idrecovery'], MCRYPT_MODE_ECB);

			$encryptCode = base64_encode($encrypt);
			
			if ($admin === true) {
				$link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=";
			} else {
				$link = "http://www.hcodecommerce.com.br/forgot/reset?code=";
			}

			$mailer = new Mailer(
				$email, 
				$data['desperson'],
				"Redefinição de senha da Hcode Store", 
				"forgot", 
			array(
				"name"=>$data['desperson'],
				"link"=>$link.$encryptCode
			));

			return $mailer->send();

		}
		else
		{

			throw new \Exception("Não foi possível redefinir a senha.");

		}

	}

	public static function validForgotDecrypt($code)
	{

		$code = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, User::FORGOT_SECRET, base64_decode($code), MCRYPT_MODE_ECB));

		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_userspasswordsrecoveries a 
			INNER JOIN tb_users b USING(iduser) 
			INNER JOIN tb_persons c USING(idperson) 
			WHERE 
				a.idrecovery = :idrecovery 
			    AND 
			    a.dtrecovery IS NULL 
			    AND 
			    DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
		", array(
			":idrecovery"=>$code
		));

		if (count($results) === 0)
		{
			throw new \Exception("Recuperação inválida.");
		}
		else
		{
			
			return $results[0];

		}

	}

	public static function setForgotUsed($idrecovery)
	{

		$sql = new Sql();

		$sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
			":idrecovery"=>$idrecovery
		));

	}

	public static function getPasswordHash($password)
	{

		return password_hash($password, PASSWORD_DEFAULT, [
			'cost'=>12
		]);

	}

	public function setPassword($password)
	{

		$sql = new Sql();

		$sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
			":password"=>User::getPasswordHash($password),
			":iduser"=>$this->getiduser()
		));

	}

	public static function checkLoginExist($login):bool
	{

		$sql = new Sql();

		$result = $sql->select("SELECT COUNT(*) AS nrtotal FROM tb_users WHERE deslogin = :login", [
			':login'=>$login
		]);

		return ((int)$result[0]['nrtotal'] > 0);

	}

	public function getOrders()
	{

		$sql = new Sql();

		return $sql->select("
			SELECT * 
			FROM tb_orders a
			INNER JOIN tb_ordersstatus b USING(idstatus)
			INNER JOIN tb_carts c USING(idcart)
			INNER JOIN tb_users d ON d.iduser = a.iduser
			INNER JOIN tb_addresses e USING(idaddress)
			INNER JOIN tb_persons f ON f.idperson = d.idperson
			WHERE a.iduser = :iduser
			ORDER BY a.dtregister DESC
		", [
			':iduser'=>$this->getiduser()
		]);

	}

}

 ?>