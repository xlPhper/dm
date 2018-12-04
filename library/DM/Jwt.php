<?php
/**
 * Jwt
 */
require_once APPLICATION_PATH . '/../vendor/autoload.php';

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class DM_Jwt
{
    /**
     * @var Token
     */
    public $token = null;

    public function test(){var_dump(113);exit;}
    /**
     *
     * @param string $AppKey
     * @param array $UserInfo
     * @param string $Salt
     * @return Token
     */
    public function create($AdminID, $UserInfo,$Time, $Salt = "qk.duomai.com")
    {
        $build = (new Builder())->setIssuer($AdminID)
            ->setAudience($AdminID)
            ->setId($AdminID)
            ->setIssuedAt(time())
            ->setNotBefore(time())
            ->setExpiration($Time)
            ->set('AdminID', $AdminID);
        foreach ($UserInfo as $key=>$value){
            $build->set($key, $value);
        }
        if($Salt){
            $build->sign($this->getSigner(),$Salt);
        }
        $this->token = $build->getToken();
        return $this->token;
    }

    public function out($token)
    {
        $this->token->setExpiration(time());
        return $this;
    }

    /**
     * @param string | Token $token
     * @return $this
     */
    public function parse($token)
    {
        $this->token = (new Parser())->parse((string) $token);
        return $this;
    }
    public function getSigner(){
        return new Sha256();
    }

    public function verify($Salt){
        return $this->token->verify($this->getSigner(),$Salt);
    }
    public function validate($AdminID){
        $data = new ValidationData();
        $data->setIssuer($AdminID);
        $data->setAudience($AdminID);
        $data->setId($AdminID);
        return  $this->token->validate($data);
    }


}