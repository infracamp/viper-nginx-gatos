<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 09.08.18
 * Time: 11:03
 */

namespace NginxUpdater;

require __DIR__ . "/../vendor/autoload.php";



$data = "[" . phore_exec("sudo docker service ls --format '{{json . }}'") . "]";
$data = json_decode($data, true);

$services = [];
foreach ($data as $cur) {
    $services[$cur["Name"]] = $cur;
}

ksort($services);

$sha = "";
if (file_exists(NGINX_CONF)) {
    $sha = sha1_file(NGINX_CONF);
}

$config = "";

foreach ($services as $serviceName => $service) {
    $inspectData = json_decode(phore_exec("sudo docker service inspect --format '{{json . }}' :ID", $service), true);

    $serverNames = "$serviceName" . CONF_HOSTNAME;
    foreach ($inspectData["Spec"]["Labels"] as $lName => $lValue) {
        if ($lName !== "cf_domain")
            continue;
        $serverNames .= " $lValue";

    }


    $config .= "\n";
    $config .= "server{listen 80; listen [::]:80; server_name $serverNames; location / { proxy_pass http://{$serverNames}:80/; } }";


}

if ($sha !== sha1($config)) {
    echo "\nRestart required.";
    file_put_contents(NGINX_CONF, $config);
    phore_exec("sudo service nginx restart");
}




