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

class AfterInstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;
        
        $basejs = file_get_contents(getcwd() . "/client/src/controllers/base.js");
        if(stripos($basejs, "secsignauth:views/login") === false){
            $basejs = str_ireplace("views/login", "secsignauth:views/login", $basejs);
            file_put_contents(getcwd() . "/client/src/controllers/base.js", $basejs);
        }
        
        $espojs = file_get_contents(getcwd() . "/client/espo.min.js");
        if(stripos($espojs, "secsignauth:views/login") === false){
            $espojs = str_ireplace("views/login", "secsignauth:views/login", $espojs);
            file_put_contents(getcwd() . "/client/espo.min.js", $espojs);
        }
        
        $preload = file_get_contents(getcwd() . "/client/cfg/pre-load.json");
        if(stripos($preload, "secsignauth:login") === false){
            $preload = str_ireplace("login", "secsignauth:login", $preload);
            file_put_contents(getcwd() . "/client/cfg/pre-load.json", $preload);
        }
        
        $app = new \Espo\Core\Application();
        $app->runRebuild();
        
        $this->clearCache();
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {}
    }
}
