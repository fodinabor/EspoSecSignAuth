<?php
/*
 Copyright (C) 2018 by Joachim Meyer
 
 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:
 
 The above copyright notice and this permission notice shall be included in
 all copies or substantial portions of the Software.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 THE SOFTWARE.
 */

namespace Espo\Modules\SecSignAuth\Controllers;

include("SecSignIDApi.php");

use \Espo\Core\Exceptions\Error,
    \Espo\Core\Exceptions\Forbidden,
    \Espo\Core\Exceptions\Unauthorized,
    \Espo\Core\Exceptions\BadRequest;

class SecSign extends \Espo\Core\Controllers\Base
{
    
    private $entityManager = null;
    private $authUser = null;
    
    private function getEntityManager(){
        if(!$this->entityManager){
            $config = $this->getContainer()->get('config');
            $params = array(
                'host' => $config->get('database.host'),
                'port' => $config->get('database.port'),
                'dbname' => $config->get('database.dbname'),
                'user' => $config->get('database.user'),
                'charset' => $config->get('database.charset', 'utf8'),
                'password' => $config->get('database.password'),
                'metadata' => $this->getContainer()->get('ormMetadata')->getData(),
                'repositoryFactoryClassName' => '\\Espo\\Core\\ORM\\RepositoryFactory',
                'driver' => $config->get('database.driver'),
                'platform' => $config->get('database.platform'),
                'sslCA' => $config->get('database.sslCA'),
                'sslCert' => $config->get('database.sslCert'),
                'sslKey' => $config->get('database.sslKey'),
                'sslCAPath' => $config->get('database.sslCAPath'),
                'sslCipher' => $config->get('database.sslCipher')
            );
            $this->entityManager = new \Espo\Core\ORM\EntityManager($params);
            $this->entityManager->setEspoMetadata($this->getContainer()->get('metadata'));
            $this->entityManager->setHookManager($this->getContainer()->get('hookManager'));
            $this->entityManager->setContainer($this->getContainer());
            
        }
        return $this->entityManager;
    }
    
    private function checkUser($request, $idincluded = false){
        $espoAuth = $request->headers('HTTP_ESPO_AUTHORIZATION');
        if (isset($espoAuth)) {
            
            list($authUsername, $authPassword) = explode(':', base64_decode($espoAuth), 2);
            if(!isset($authUsername) || !isset($authPassword))
                return false;
            
            $config = $this->getContainer()->get('config');
            $pwhash = new \Espo\Core\Utils\PasswordHash($config);
            
            if($idincluded){
                list($secSignRequestId, $authPassword) = explode("__", $authPassword, 2);
                $hash = $pwhash->hash($authPassword);
                
                $user = $this->getEntityManager()->getRepository('User')->findOne(array(
                    'whereClause' => array(
                        'userName' => $authUsername,
                        'password' => $hash,
                        'secSignRequestId' => $secSignRequestId
                    )
                ));
            } else {
                $hash = $pwhash->hash($authPassword);
                
                $user = $this->getEntityManager()->getRepository('User')->findOne(array(
                    'whereClause' => array(
                        'userName' => $authUsername,
                        'password' => $hash
                    )
                ));
            }
            
            if ($user) {
                if (!$user->isActive()) {
                    $GLOBALS['log']->error("AUTH: Trying to login as user '".$user->get('userName')."' which is not active.");
                    return false;
                }

                $user->set('ipAddress', $_SERVER['REMOTE_ADDR']);

                $this->authUser = $user;

                return true;
            }
        }
        return false;
    }
    
    public function actionRequest($params, $data, $request)
    {
        if (!$this->checkUser($request)) {
            throw new Unauthorized();
        }
        
        $secSignId = $this->authUser->get('emailAddress');
        
        try
        {
            $secSignIDApi = new \SecSignIDApi();
            $config = $this->getContainer()->get('config');
            
            $authsession = $secSignIDApi->requestAuthSession($secSignId, 'CRM Gebetshaus', $config->get('siteUrl'));
            
            $this->authUser->set('secSignRequestId', $authsession->getRequestID());
            $this->authUser->set('secSignRequestTime', date('Y-m-d H:i:s'));
            $this->getEntityManager()->getRepository('User')->save($this->authUser);
        }
        catch(Exception $e)
        {
            throw new BadRequest();
        }

        return [
                    'secsignid' => $authsession->getSecSignID(),
                    'authsessionid' => $authsession->getAuthSessionID(),
                    'requestid' => $authsession->getRequestID(),
                    'servicename' => $authsession->getRequestingServiceName(),
                    'serviceaddress' => $authsession->getRequestingServiceAddress(),
                    'img' => $authsession->getIconData()
                ];
    }
    
    public function postActionValidate($params, $data, $request)
    {
        if (!$this->checkUser($request, true)) {
            throw new Unauthorized();
        }
        if ($data == null) {
            throw new BadRequest();
        }

        try
        {
            
            $authsession = new \AuthSession();
            $authsession->createAuthSessionFromArray(array(
               'requestid' => $data->requestid,
               'secsignid' => $data->secsignid,
               'authsessionid' => $data->authsessionid,
               'servicename' => $data->servicename,
               'serviceaddress' => $data->serviceaddress
            ));
            
            $secSignIDApi = new \SecSignIDApi();

            if(time() - strtotime($this->authUser->get('secSignRequestTime')) > 120){
                $secSignIDApi->cancelAuthSession($authsession);
                return [ 'status' => 'timeout' ];
            }

            $authSessionState = $secSignIDApi->getAuthSessionState($authsession);
        
            if($authSessionState == \AuthSession::AUTHENTICATED)
            {
               $secSignIDApi->releaseAuthSession($authsession);
               return [ 'status' => 'authenticated' ];
            }
            else if($authSessionState == \AuthSession::DENIED)
            {
               return [ 'status' => 'denied' ];
            }
            else if(($authSessionState == \AuthSession::PENDING) || ($authSessionState == \AuthSession::FETCHED))
            {
               return [ 'status' => 'pending' ];
            }
        }
        catch(Exception $e)
        {
            throw new BadRequest();
        }
        return [ 'status' => 'unknown' ];
    }
    
    public function actionUse($params, $data, $request){
        return [ "useSecSign" => ($this->getContainer()->get('config')->get('authenticationMethod') == "SecSignAuth") ];
    }
}

