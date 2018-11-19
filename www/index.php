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
use Infracamp\Gatos\ServiceViewCtrl;
use Phore\MicroApp\App;
use Phore\MicroApp\Auth\BasicUserProvider;
use Phore\MicroApp\Auth\HttpBasicAuthMech;
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
$app->authManager->setAuthMech(new HttpBasicAuthMech());
$app->authManager->setUserProvider($up = new BasicUserProvider());
$up->addUser("admin", '$6$WJOnadB0$DFIV/H2eKjayXekj6Zj6NtPOsrcZWW55QERBvMLM70FodQiEKowC2MmKHuboACJgFjSyDRp5BzE6Qm1vlP0rL1', "@admin", []);
// Set Authentication

$app->acl->addRule(\aclRule()->route("/deploy/*")->ALLOW());
$app->acl->addRule(\aclRule()->role("@user")->ALLOW());

set_time_limit(180);

$app->addModule(new Bootstrap4Module());

$app->router->delegate("/", InfoCtrl::class);
$app->router->delegate("/logs/:serviceId", ServiceViewCtrl::class);

$app->router->on("/deploy/::registryPath", ["GET", "POST"], function (RouteParams $routeParams, Request $request) {
    $key = $request->GET->get("secret");
    if ($key !== CONF_DEPLOY_KEY)
        throw new HttpException("Authorisation (deploy key) failed", 403);

    $registry = $routeParams->get("registryPath");

    if ( ! fnmatch(CONF_ALLOW_REGISTRY_IMAGE, $registry))
        throw new \InvalidArgumentException("Deployment of image '$registry' is not allowed. Allowed path: '" . CONF_ALLOW_REGISTRY_IMAGE . "'");

    $serviceName = basename($registry);

    $cmd = new DockerCmd();
    try {
        $cmd->dockerLogin(CONF_REGISTRY_LOGIN_USER, CONF_REGISTRY_LOGIN_PASS, explode("/", $registry)[0]);
    } catch (\Exception $e) {
        throw new HttpException("Registry authentication failed. (docker login): Check your CONF_REGISTRY_PREFIX_* and CONF_REGISTY_LOGIN_* are correct.",521);
    }

    $rudlConfig = [];
    if ($request->requestMethod === "POST") {
        $data = file_get_contents("php://input");
        $kickConfig = yaml_parse($data);
        if ($kickConfig === false)
            throw new HttpException("Cannot parse POST payload data. No valid yaml content.", 522);
        if (  ! isset ($kickConfig["deploy"])) {
            throw new HttpException("No deploy section found in post body.");
        }
        if ( ! isset($kickConfig["deploy"][CONF_CLUSTER_HOSTNAME]))
            throw new HttpException("No deploy config for cluster '" . CONF_CLUSTER_HOSTNAME . "' in deploy section");
        $rudlConfig = $kickConfig["deploy"][CONF_CLUSTER_HOSTNAME];
    }



    $updateType = $cmd->serviceDeploy($serviceName, $registry, $rudlConfig);

    $error = null;
    $startTime = time();
    while (true) {
        sleep(1);
        $status = $cmd->getServiceState($serviceName);

        if ((time()-300) > $startTime) {
            if ($updateType !== "create")
                $error = "Timeout: No status in 120 seconds. ({$status["CurrentState"]})";
            break;
        }

        if ($status["Error"] !== null) {
            $error = $status["Error"];
            break;
        }

        if ($status["State"] === "Running" ) {
            $error = null;
            break;
        }
    }
    if ($error !== null)
        throw new HttpException(json_encode(["registry" => $registry, "serviceName" => $serviceName, "type"=>$updateType, "error"=>$error, "current_state"=>$status["CurrentState"]]), 520);

    return ["success"=>true, "registry" => $registry, "serviceName" => $serviceName, "type"=>$updateType, "current_state"=>$status["CurrentState"]];
    //return true;
});


$app->serve();
