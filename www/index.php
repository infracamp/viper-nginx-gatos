<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 10:55
 */

namespace App;

use Infracamp\Gatos\DockerCmd;
use Infracamp\Gatos\InfoCtrl;
use Phore\MicroApp\App;
use Phore\MicroApp\Exception\HttpException;
use Phore\MicroApp\Handler\JsonExceptionHandler;
use Phore\MicroApp\Handler\JsonResponseHandler;
use Phore\MicroApp\Type\Request;
use Phore\MicroApp\Type\RouteParams;
use Phore\Theme\Bootstrap\Bootstrap4Module;

require __DIR__ . "/../vendor/autoload.php";





$app = new App();
$app->activateExceptionErrorHandlers();
$app->setOnExceptionHandler(new JsonExceptionHandler());
$app->setResponseHandler(new JsonResponseHandler());
// Set Authentication

$app->acl->addRule(\aclRule()->route("/*")->ALLOW());


set_time_limit(90);

$app->addModule(new Bootstrap4Module());

$app->router->delegate("/", InfoCtrl::class);


$app->router->on("/deploy/::path", ["GET", "POST"], function (RouteParams $routeParams, Request $request) {
    if ($request->GET->get("key") !== CONF_DEPLOY_KEY)
        throw new HttpException("Authorisation (deploy key) failed", 403);



    $registry = CONF_REGISTRY_PREFIX . "/" . $routeParams->get("path");
    $serviceName = basename($registry);

    $cmd = new DockerCmd();
    try {
        $cmd->dockerLogin(CONF_REGISTRY_LOGIN_USER, CONF_REGISTRY_LOGIN_PASS, explode("/", CONF_REGISTRY_PREFIX)[0]);
    } catch (\Exception $e) {
        throw new HttpException("Registry authentication failed. (docker login): Check your CONF_REGISTRY_PREFIX_* and CONF_REGISTY_LOGIN_* are correct.",521);
    }

    $rudlConfig = [];
    if ($request->requestMethod === "POST") {
        $data = file_get_contents("php://input");
        $rudlConfig = yaml_parse($data);
        if ($rudlConfig === false)
            throw new HttpException("Cannot parse POST payload data. No valid yaml content.", 522);
    }

    $label = CONFIG_SERVICE_LABEL ."=" . json_encode($rudlConfig);

    $updateType = $cmd->serviceDeploy($serviceName, $registry, $label);

    $error = null;
    $startTime = time();
    while (true) {
        sleep(1);
        $status = $cmd->getServiceInspect($serviceName);
        if ((time()-20) > $startTime) {
            $error = "Timeout: No status in 20 seconds. (State: '{$status["UpdateStatus"]["State"]}' Message: '{$status["UpdateStatus"]["Message"]}')";
            break;
        }

        if ( ! isset ($status["UpdateStatus"]))
            continue;

        if ($status["UpdateStatus"]["State"] == "completed") {
            $error = null;
            break;
        }
        if ($status["UpdateStatus"]["State"] == "paused") {
            $error = $status["UpdateStatus"]["Message"];
            break;
        }

    }

    if($error !== null) {
        throw new HttpException(json_encode(["registry" => $registry, "serviceName" => $serviceName, "type"=>$updateType, "success"=>false, "error"=>$error]), 520);
    }
    return ["success"=>true, "registry" => $registry, "serviceName" => $serviceName, "type"=>$updateType];
    //return true;
});


$app->serve();
