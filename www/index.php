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


set_time_limit(3600);

$app->addModule(new Bootstrap4Module());

$app->router->delegate("/", InfoCtrl::class);

$app->router->get("/deploy/::path", function (RouteParams $routeParams, Request $request) {
    if ($request->GET->get("key") !== CONF_DEPLOY_KEY)
        throw new HttpException("Authorisation failed", 403);



    $registry = CONF_REGISTRY_PREFIX . "/" . $routeParams->get("path");
    $serviceName = basename($registry);

    $cmd = new DockerCmd();
    $runningServices = $cmd->getServiceList();
    phore_exec("sudo docker login -u :user -p :pass :registry", [
        "registry" => explode("/", CONF_REGISTRY_PREFIX)[0],
        "user" => CONF_REGISTRY_LOGIN_USER,
        "pass" => CONF_REGISTRY_LOGIN_PASS
    ]);
    if ( ! isset($runningServices[$serviceName])) {
        phore_exec("sudo timeout 30 docker service create --name :name --network :network --with-registry-auth :image", ["name" => $serviceName, "image" => $registry, "network"=>DOCKER_DEFAULT_NET]);
        $type="create";
    } else {
        phore_exec("sudo timeout 30 docker service update :name --force --with-registry-auth", ["name" => $serviceName]);
        $type="update";
    }


    return ["registry" => $registry, "serviceName" => $serviceName, "type"=>$type];
    //return true;
});


$app->router->post("/deploy/::path", function (RouteParams $routeParams, Request $request) {
    if ($request->GET->get("key") !== CONF_DEPLOY_KEY)
        throw new HttpException("Authorisation failed", 403);



    $registry = CONF_REGISTRY_PREFIX . "/" . $routeParams->get("path");
    $serviceName = basename($registry);

    $cmd = new DockerCmd();
    $runningServices = $cmd->getServiceList();
    phore_exec("sudo docker login -u :user -p :pass :registry", [
        "registry" => explode("/", CONF_REGISTRY_PREFIX)[0],
        "user" => CONF_REGISTRY_LOGIN_USER,
        "pass" => CONF_REGISTRY_LOGIN_PASS
    ]);

    $data = file_get_contents("php://input");
    $parsed = yaml_parse($data);
    if ($parsed === false)
        throw new \InvalidArgumentException("Cannot parse POST payload data. No valid yaml content.");

    $label = "org.infracamp.rudl.config=" . json_encode($parsed);



    if ( ! isset($runningServices[$serviceName])) {
        phore_exec("sudo timeout 30 docker service create --name :name --network :network --label-add :label --with-registry-auth :image", ["label"=>$label, "name" => $serviceName, "image" => $registry, "network"=>DOCKER_DEFAULT_NET]);
        $type="create";
    } else {
        phore_exec("sudo timeout 30 docker service update :name --with-registry-auth --image --label-add :label", ["name" => $serviceName, "label"=> $label]);
        $type="update";
    }


    return ["registry" => $registry, "serviceName" => $serviceName, "type"=>$type];
    //return true;
});


$app->serve();
