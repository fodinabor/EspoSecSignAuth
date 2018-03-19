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

namespace Espo\Core\Utils\Authentication;

use \Espo\Core\Exceptions\Error;

class SecSignAuth extends Base
{
    public function login($username, $password, \Espo\Entities\AuthToken $authToken = null)
    {
        if ($authToken) {
            $hash = $authToken->get('hash');
            $user = $this->getEntityManager()->getRepository('User')->findOne(array(
                'whereClause' => array(
                    'userName' => $username,
                    'password' => $hash
                )
            ));
        } else {
            list($secSignRequestID, $password) = explode("__", $password, 2);
            $hash = $this->getPasswordHash()->hash($password);
            
            $user = $this->getEntityManager()->getRepository('User')->findOne(array(
                'whereClause' => array(
                    'userName' => $username,
                    'password' => $hash,
                    'secSignRequestID' => $secSignRequestId
                )
            ));
            
            if($user && time() - strtotime($user->get('secSignRequestTime')) > 120){
                return null;
            }
        }

        return $user;
    }
}

